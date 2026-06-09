<?php

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

test('admin can create suppliers from the page', function () {
    $user = User::factory()->create(['role' => 'admin', 'supplier_id' => null]);

    Livewire::actingAs($user)
        ->test('pages::suppliers')
        ->set('name', 'Acme Car Rentals')
        ->set('code', ' acme ')
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
        'max_users' => 8,
        'contact_name' => 'Jane Admin',
        'email' => 'ops@example.com',
        'phone' => '+52 555 0100',
        'status' => 'inactive',
    ]);
});

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
