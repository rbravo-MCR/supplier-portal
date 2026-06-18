<?php

use App\Models\Supplier;
use App\Models\User;

test('registration screen renders active supplier ids and uppercase names from the suppliers table', function () {
    $supplier = Supplier::factory()->create(['name' => 'America Car Rental', 'code' => 'ACR']);
    $inactiveSupplier = Supplier::factory()->inactive()->create(['name' => 'Inactive Rental', 'code' => 'INACTIVE']);

    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertSee('name="supplier_id"', false)
        ->assertSee('name="preferred_locale"', false)
        ->assertSee('Español')
        ->assertSee('English')
        ->assertSee('Português')
        ->assertSee('Français')
        ->assertSee('font-medium text-zinc-950!', false)
        ->assertSee('[&>option]:text-zinc-950', false)
        ->assertSee('text-zinc-950!', false)
        ->assertSee('Selecciona proveedor')
        ->assertSee('AMERICA CAR RENTAL · ACR')
        ->assertSee('value="'.$supplier->id.'"', false)
        ->assertDontSee($supplier->name)
        ->assertDontSee($inactiveSupplier->name)
        ->assertDontSee($inactiveSupplier->code);
});

test('registration screen explains when there are no active suppliers', function () {
    Supplier::factory()->inactive()->create(['name' => 'Inactive Rental', 'code' => 'INACTIVE']);

    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertSee('No hay proveedores activos disponibles para registro.')
        ->assertDontSee('INACTIVE');
});

test('new users can register', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->post(route('register.store'), [
        'supplier_id' => $supplier->id,
        'name' => 'John Doe',
        'username' => 'jdoe',
        'email' => 'test@example.com',
        'preferred_locale' => 'pt',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('supplier.dashboard', absolute: false));

    $this->assertAuthenticated();

    expect($user->role)->toBe('supplier_admin')
        ->and($user->portalRole->code)->toBe('supplier_admin')
        ->and($user->supplier_id)->toBe($supplier->id)
        ->and($user->username)->toBe('jdoe')
        ->and($user->preferred_locale)->toBe('pt')
        ->and($user->status)->toBe('inactive');
});

test('new users can register without email', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->post(route('register.store'), [
        'supplier_id' => $supplier->id,
        'name' => 'Roberto Bravo',
        'username' => 'rbravo',
        'email' => '',
        'preferred_locale' => 'es',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('username', 'rbravo')->first();

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('supplier.dashboard', absolute: false));

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->preferred_locale)->toBe('es')
        ->and($user->role)->toBe('supplier_admin');
});
