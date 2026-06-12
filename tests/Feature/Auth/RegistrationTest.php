<?php

use App\Models\Supplier;
use App\Models\User;

test('registration screen renders supplier ids and uppercase names from the suppliers table', function () {
    $supplier = Supplier::factory()->inactive()->create(['name' => 'America Car Rental', 'code' => 'ACR']);

    $response = $this->get(route('register'));

    $response
        ->assertOk()
        ->assertSee('name="supplier_id"', false)
        ->assertSee('text-zinc-950!', false)
        ->assertSee('placeholder:text-zinc-600!', false)
        ->assertSee('Selecciona proveedor')
        ->assertSee('AMERICA CAR RENTAL')
        ->assertSee('value="'.$supplier->id.'"', false)
        ->assertDontSee($supplier->name)
        ->assertDontSee($supplier->code);
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
        ->and($user->portalRole->code)->toBe('supplier_admin')
        ->and($user->supplier_id)->toBe($supplier->id)
        ->and($user->username)->toBe('jdoe')
        ->and($user->status)->toBe('inactive');
});
