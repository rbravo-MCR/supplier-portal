<?php

use App\Models\Supplier;
use App\Models\User;

test('portal sidebar shows primary sections', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Supplier portal')
        ->assertSee(__('Reservas'))
        ->assertSee(__('Precios'))
        ->assertSee(__('Importaciones'))
        ->assertSee(__('Usuarios'))
        ->assertSee(__('Proveedores'))
        ->assertSee(__('Oficinas'))
        ->assertSee(__('Categorías'))
        ->assertSee(__('Auditoría'))
        ->assertSee(__('Estado'));
});

test('supplier admins do not see suppliers menu in the sidebar', function () {
    $supplier = Supplier::factory()->create(['name' => 'Alamo', 'code' => 'ALAMO']);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('supplier.dashboard'))
        ->assertOk()
        ->assertSee('Supplier portal')
        ->assertSee(__('Reservas'))
        ->assertSee(__('Precios'))
        ->assertDontSee(__('Proveedores'));
});

test('portal status page shows recovery checks to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('portal.status'))
        ->assertOk()
        ->assertSee(__('Estado'))
        ->assertSee(__('Resumen operativo'))
        ->assertSee(__('Base de datos'))
        ->assertDontSee(__('Pendiente de conectar con el módulo correspondiente.'));
});

test('portal status page renders when configured redis client is unavailable', function () {
    $user = User::factory()->create();

    config([
        'cache.default' => 'redis',
        'database.redis.client' => 'phpredis',
    ]);

    $this->actingAs($user)
        ->get(route('portal.status'))
        ->assertOk()
        ->assertSee(__('Redis'))
        ->assertSee(__('Redis client is not installed for the configured driver.'));
})->skip(class_exists('Redis'), 'PhpRedis is installed in this environment.');
