<?php

use App\Models\Supplier;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertSee('Iniciar sesión')
        ->assertSee('Portal operativo para proveedores')
        ->assertSee('Entrar al portal')
        ->assertDontSee('name="supplier_code"', false)
        ->assertDontSee('Selecciona proveedor')
        ->assertSee('name="username"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="remember"', false)
        ->assertSee('[&_[data-flux-label]]:text-zinc-900', false)
        ->assertSee('placeholder:text-zinc-600!', false)
        ->assertDontSee('Administración interna');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'username' => 'admin',
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('supplier users are redirected to the supplier panel', function () {
    $supplier = Supplier::factory()->create(['code' => 'ALAMO']);
    $user = User::factory()->create([
        'username' => 'operador',
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'operador',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('supplier.dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('authenticated supplier users visiting login are redirected to the supplier dashboard', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
        'current_team_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('supplier.dashboard'));
});

test('platform admins are redirected to the admin dashboard', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => null,
        'username' => 'admin',
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create(['username' => 'admin']);

    $response = $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('username');

    $this->assertGuest();
});

test('disabled users cannot authenticate', function () {
    $user = User::factory()->create([
        'username' => 'admin',
        'status' => 'disabled',
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('username');

    $this->assertGuest();
});

test('supplier users without supplier cannot authenticate', function () {
    $user = User::factory()->create([
        'username' => 'operador',
        'role' => 'supplier_admin',
        'supplier_id' => null,
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'operador',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('username');

    $this->assertGuest();
});

test('supplier users with inactive supplier cannot authenticate', function () {
    $supplier = Supplier::factory()->inactive()->create(['code' => 'ALAMO']);
    $user = User::factory()->create([
        'username' => 'operador',
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'operador',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('username');

    $this->assertGuest();
});

test('login authenticates by username and password without selecting supplier', function () {
    $alamo = Supplier::factory()->create(['code' => 'ALAMO']);

    $alamoUser = User::factory()->create([
        'username' => 'operador',
        'role' => 'supplier_admin',
        'supplier_id' => $alamo->id,
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'operador',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('supplier.dashboard', absolute: false));

    $this->assertAuthenticatedAs($alamoUser);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
