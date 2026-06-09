<?php

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

test('portal placeholder pages are available to authenticated users', function (string $routeName, string $title) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($title)
        ->assertSee(__('Pendiente de conectar con el módulo correspondiente.'));
})->with([
    'suppliers' => ['portal.suppliers', 'Proveedores'],
    'status' => ['portal.status', 'Estado'],
]);
