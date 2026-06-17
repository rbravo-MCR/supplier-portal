<?php

use App\Models\Country;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

test('platform users can view the suppliers directory', function () {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);
    Supplier::factory()->create([
        'name' => 'Cancun Local Cars',
        'code' => 'CLC',
    ]);

    $this->actingAs($user)
        ->get(route('portal.suppliers'))
        ->assertOk()
        ->assertSee('Proveedores')
        ->assertSee('Cancun Local Cars')
        ->assertSee('CLC')
        ->assertSee('data-test="supplier-add-button"', false);
});

test('supplier users cannot view the suppliers directory', function () {
    $user = User::factory()->create(['role' => 'supplier_user']);

    $this->actingAs($user)
        ->get(route('portal.suppliers'))
        ->assertForbidden();
});

test('supplier scoped admins cannot view the suppliers directory', function () {
    $supplier = Supplier::factory()->create(['name' => 'Alamo', 'code' => 'ALAMO']);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.suppliers'))
        ->assertForbidden();
});

test('admin can create suppliers from the page', function () {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);
    $country = Country::factory()->create([
        'name' => 'United States',
        'iso2' => 'US',
    ]);

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->set('name', 'Acme Car Rentals')
        ->set('code', ' acme ')
        ->set('countryId', $country->id)
        ->set('integrationType', 'none')
        ->set('status', 'inactive')
        ->set('maxUsers', 8)
        ->set('contactName', 'Jane Admin')
        ->set('email', 'OPS@EXAMPLE.COM')
        ->set('phone', '+52 555 0100')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('suppliers', [
        'name' => 'Acme Car Rentals',
        'code' => 'ACME',
        'country_id' => $country->id,
        'integration_type' => 'none',
        'max_users' => 8,
        'contact_name' => 'Jane Admin',
        'email' => 'ops@example.com',
        'phone' => '+52 555 0100',
        'status' => 'inactive',
    ]);
});

test('supplier creation shows every active country outside mexico', function () {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);
    Country::factory()->create(['name' => 'México', 'iso2' => 'MX']);

    foreach (['AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ', 'BA', 'BB', 'BC', 'BD', 'ZZ'] as $index => $iso2) {
        Country::factory()->create([
            'name' => sprintf('Country %02d', $index + 1),
            'iso2' => $iso2,
        ]);
    }

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->assertSee('Country 31 · ZZ')
        ->assertDontSee('México · MX');
});

test('admin cannot create suppliers based in mexico', function () {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);
    $mexico = Country::factory()->create([
        'name' => 'México',
        'iso2' => 'MX',
    ]);

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->set('name', 'Mexico Cars')
        ->set('code', 'MXCARS')
        ->set('countryId', $mexico->id)
        ->set('integrationType', 'none')
        ->call('save')
        ->assertHasErrors(['countryId']);

    $this->assertDatabaseMissing('suppliers', [
        'code' => 'MXCARS',
    ]);
});

test('admin cannot create suppliers with api or soap integrations', function (string $integrationType) {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);
    $country = Country::factory()->create([
        'name' => 'United States',
        'iso2' => 'US',
    ]);

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->set('name', 'Integrated Cars')
        ->set('code', 'INTEGRATED')
        ->set('countryId', $country->id)
        ->set('integrationType', $integrationType)
        ->call('save')
        ->assertHasErrors(['integrationType']);

    $this->assertDatabaseMissing('suppliers', [
        'code' => 'INTEGRATED',
    ]);
})->with(['api', 'soap']);

test('supplier search filters by code', function () {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);
    Supplier::factory()->create(['name' => 'Visible Supplier', 'code' => 'VISIBLE']);
    Supplier::factory()->create(['name' => 'Hidden Supplier', 'code' => 'HIDDEN']);

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->set('search', 'VISIBLE')
        ->assertSee('Visible Supplier')
        ->assertDontSee('Hidden Supplier');
});

test('admin can toggle supplier status from the page', function () {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);
    $supplier = Supplier::factory()->create(['status' => 'active']);

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->call('toggleStatus', $supplier->id)
        ->assertHasNoErrors();

    expect($supplier->refresh()->status)->toBe('inactive');
});

test('auditor cannot toggle supplier status from the page', function () {
    $user = User::factory()->create(['role' => 'auditor', 'supplier_id' => null]);
    $supplier = Supplier::factory()->create(['status' => 'active']);

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->call('toggleStatus', $supplier->id)
        ->assertForbidden();

    expect($supplier->refresh()->status)->toBe('active');
});
