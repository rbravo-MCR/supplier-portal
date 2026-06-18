<?php

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;

test('audit page shows outlet versus supplier booking comparison', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'Proveedor Demo',
        'code' => 'DEMO',
    ]);
    $user = User::factory()->create();

    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'OUTLET-001',
        'customer_name' => 'Cliente Pendiente',
        'status' => 'pending',
        'created_at' => '2026-06-05 09:15:00',
    ]);
    Booking::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'SUPPLIER-002',
        'customer_name' => 'Cliente Confirmado',
        'status' => 'confirmed',
        'created_at' => '2026-06-05 12:30:00',
    ]);

    $this->actingAs($user)
        ->get(route('portal.audit'))
        ->assertOk()
        ->assertSee('Desglose de reservas')
        ->assertSee('Reservas Outlet')
        ->assertSee('Pendientes supplier')
        ->assertSee('Confirmadas supplier')
        ->assertSee('Número reserva')
        ->assertSee('Fecha reserva')
        ->assertSee('OUTLET-001')
        ->assertSee('Falta número reserva')
        ->assertSee('Pendiente')
        ->assertSee('SUPPLIER-002')
        ->assertSee('Confirmada')
        ->assertDontSee('Cliente Pendiente')
        ->assertDontSee('Cliente Confirmado')
        ->assertSee('05/06/2026 03:15')
        ->assertSee('05/06/2026 06:30');
});

test('supplier user only sees own booking comparison', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'Proveedor Propio',
        'code' => 'OWN',
    ]);
    $otherSupplier = Supplier::factory()->create([
        'name' => 'Proveedor Ajeno',
        'code' => 'OTHER',
    ]);
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);

    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'OWN-001',
        'status' => 'pending',
    ]);
    Booking::factory()->confirmed()->create([
        'supplier_id' => $otherSupplier->id,
        'reservation_code' => 'OTHER-001',
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get(route('portal.audit'))
        ->assertOk()
        ->assertSee('OWN-001')
        ->assertDontSee('OTHER-001');
});
