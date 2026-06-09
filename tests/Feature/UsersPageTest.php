<?php

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('users page displays portal users', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $admin = User::factory()->create();

    User::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'María López',
        'username' => 'mlopez',
        'email' => 'maria@example.test',
        'role' => 'supplier_admin',
    ]);

    $this->actingAs($admin)
        ->get(route('portal.users'))
        ->assertOk()
        ->assertSee('Directorio de usuarios')
        ->assertSee('María López')
        ->assertSee('mlopez')
        ->assertSee('maria@example.test')
        ->assertSee('DEMO')
        ->assertSee('Administrador');
});

test('users form creates a portal user', function () {
    $supplier = Supplier::factory()->create();
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::users')
        ->set('supplierId', $supplier->id)
        ->set('name', 'Carlos Pérez')
        ->set('username', 'cperez')
        ->set('email', 'carlos@example.test')
        ->set('password', 'temporary-password')
        ->set('role', 'supplier_pricing')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Carlos Pérez')
        ->assertSee('cperez')
        ->assertSee('carlos@example.test');

    $this->assertDatabaseHas('users', [
        'supplier_id' => $supplier->id,
        'name' => 'Carlos Pérez',
        'username' => 'cperez',
        'email' => 'carlos@example.test',
        'role' => 'supplier_pricing',
        'status' => 'active',
    ]);
});

test('users page reads active suppliers from cached array rows', function () {
    $supplier = Supplier::factory()->create(['name' => 'Cached Supplier', 'code' => 'CACHED']);
    $admin = User::factory()->create();

    Cache::put('suppliers.active', [
        ['id' => $supplier->id, 'name' => $supplier->name, 'code' => $supplier->code],
    ]);

    Livewire::actingAs($admin)
        ->test('pages::users')
        ->assertSee('Cached Supplier');
});
