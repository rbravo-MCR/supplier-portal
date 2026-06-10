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

test('portal placeholder pages are available to authenticated users', function (string $routeName, string $title) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($title)
        ->assertSee(__('Pendiente de conectar con el módulo correspondiente.'));
})->with([
    'status' => ['portal.status', 'Estado'],
]);
