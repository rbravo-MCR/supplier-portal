<?php

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use Livewire\Livewire;

test('bookings page displays only pending bookings', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);

    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'OUTLET-PENDING',
        'customer_name' => 'Cliente Pendiente',
        'status' => 'pending',
    ]);

    Booking::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'OUTLET-CONFIRMED',
        'customer_name' => 'Cliente Confirmado',
    ]);

    $this->actingAs($user)
        ->get(route('portal.bookings'))
        ->assertOk()
        ->assertSee('Reservas pendientes')
        ->assertSee('Pendiente')
        ->assertSee('OUTLET-PENDING')
        ->assertSee('Cliente Pendiente')
        ->assertDontSee('OUTLET-CONFIRMED')
        ->assertDontSee('Cliente Confirmado');
});

test('pending booking can be confirmed with manual reservation number', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);
    $booking = Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'OUTLET-PENDING',
        'status' => 'pending',
    ]);

    Livewire::actingAs($user)
        ->test('pages::bookings')
        ->set("reservationCodes.{$booking->id}", 'manual-12345')
        ->call('confirm', $booking->id)
        ->assertHasNoErrors()
        ->assertDontSee('OUTLET-PENDING');

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'reservation_code' => 'MANUAL-12345',
        'status' => 'confirmed',
    ]);

    $this->assertDatabaseHas('booking_actions', [
        'booking_id' => $booking->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'action' => 'confirmed',
    ]);

    $this->assertDatabaseHas('outbox_events', [
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'event_type' => 'BookingConfirmed',
        'status' => 'pending',
    ]);
});
