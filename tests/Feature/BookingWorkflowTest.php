<?php

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Booking\Application\DTOs\ConfirmBookingData;
use App\Modules\Booking\Application\DTOs\RejectBookingData;
use App\Modules\Booking\Application\UseCases\ConfirmBooking;
use App\Modules\Booking\Application\UseCases\ListPendingBookings;
use App\Modules\Booking\Application\UseCases\RejectBooking;
use App\Modules\Booking\Application\UseCases\ViewBooking;
use App\Modules\Booking\Domain\Exceptions\BookingMustBePending;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

test('supplier only sees own pending bookings', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);

    $ownPending = Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'OWN-PENDING',
        'status' => 'pending',
    ]);
    Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'reservation_code' => 'OWN-CONFIRMED',
        'status' => 'confirmed',
    ]);
    Booking::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'reservation_code' => 'OTHER-PENDING',
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $bookings = app(ListPendingBookings::class)->handle();

    expect($bookings->getCollection()->pluck('id')->all())->toBe([$ownPending->id]);
});

test('supplier cannot access another supplier booking and attempt is audited', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);
    $booking = Booking::factory()->create(['supplier_id' => $otherSupplier->id]);

    $this->actingAs($user);

    try {
        app(ViewBooking::class)->handle($booking->id, $user);
    } catch (AuthorizationException) {
        $this->assertDatabaseHas('audit_logs', [
            'supplier_id' => $booking->supplier_id,
            'user_id' => $user->id,
            'module' => 'booking',
            'action' => 'access_denied',
            'entity_type' => Booking::class,
            'entity_id' => $booking->id,
        ]);

        return;
    }

    $this->fail('Expected authorization exception.');
});

test('supplier can confirm own pending booking', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);
    $booking = Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $confirmed = app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $user->id));

    expect($confirmed->status)->toBe('confirmed');

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

    $this->assertDatabaseHas('audit_logs', [
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'module' => 'booking',
        'action' => 'confirmed',
        'entity_type' => Booking::class,
        'entity_id' => $booking->id,
    ]);
});

test('supplier can reject own pending booking with reason', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);
    $booking = Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $rejected = app(RejectBooking::class)->handle(new RejectBookingData($booking->id, $user->id, 'No vehicle available'));

    expect($rejected->status)->toBe('rejected');

    $this->assertDatabaseHas('booking_actions', [
        'booking_id' => $booking->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'action' => 'rejected',
        'reason' => 'No vehicle available',
    ]);

    $this->assertDatabaseHas('outbox_events', [
        'aggregate_type' => Booking::class,
        'aggregate_id' => $booking->id,
        'event_type' => 'BookingRejected',
        'status' => 'pending',
    ]);
});

test('rejecting a booking requires a reason', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);
    $booking = Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    app(RejectBooking::class)->handle(new RejectBookingData($booking->id, $user->id, ''));
})->throws(ValidationException::class);

test('confirmed bookings cannot be confirmed again', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);
    $booking = Booking::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'confirmed',
    ]);

    $this->actingAs($user);

    app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $user->id));
})->throws(BookingMustBePending::class);
