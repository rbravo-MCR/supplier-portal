<?php

use App\Models\Supplier;
use App\Models\User;

test('registration screen can be rendered', function () {
    $supplier = Supplier::factory()->create(['name' => 'Alamo', 'code' => 'ALAMO']);

    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertSee('name="supplier_id"', false)
        ->assertSee('Selecciona proveedor')
        ->assertSee($supplier->name)
        ->assertSee($supplier->code);
});

test('new users can register', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->post(route('register.store'), [
        'supplier_id' => $supplier->id,
        'name' => 'John Doe',
        'username' => 'jdoe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('supplier.dashboard', absolute: false));

    $this->assertAuthenticated();

    expect($user->role)->toBe('supplier_admin')
        ->and($user->supplier_id)->toBe($supplier->id)
        ->and($user->username)->toBe('jdoe')
        ->and($user->status)->toBe('active');
});
