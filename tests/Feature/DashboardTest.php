<?php

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('portal.imports'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the admin dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('admin.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Trabajando, leyendo datos...');
});

test('supplier users can visit the supplier dashboard', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('supplier.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Trabajando, leyendo datos...');
});

test('dashboard shows booking status bars for the selected date range', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'supplier_id' => $supplier->id,
        'role' => 'supplier_user',
    ]);

    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
        'created_at' => '2026-06-05 10:00:00',
    ]);
    Booking::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'status' => 'confirmed',
        'created_at' => '2026-06-06 10:00:00',
    ]);
    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'cancelled',
        'created_at' => '2026-06-07 10:00:00',
    ]);
    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
        'created_at' => '2026-05-31 10:00:00',
    ]);

    Livewire::actingAs($user)
        ->test('booking-dashboard')
        ->set('startDate', '2026-06-01')
        ->set('endDate', '2026-06-30')
        ->assertSee('Pendientes')
        ->assertSee('Confirmadas')
        ->assertSee('Canceladas')
        ->assertSee('Total en el periodo')
        ->assertSee('1');
});

test('dashboard only counts bookings for the authenticated supplier', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'supplier_id' => $supplier->id,
        'role' => 'supplier_user',
    ]);

    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
        'created_at' => '2026-06-05 10:00:00',
    ]);
    Booking::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'status' => 'pending',
        'created_at' => '2026-06-05 10:00:00',
    ]);

    Livewire::actingAs($user)
        ->test('booking-dashboard')
        ->set('startDate', '2026-06-01')
        ->set('endDate', '2026-06-30')
        ->assertSet('totalBookings', 1);
});
