<?php

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Booking\Application\Contracts\BookingRepository;
use App\Modules\Booking\Application\DTOs\ConfirmBookingData;
use App\Modules\Booking\Application\DTOs\RejectBookingData;
use App\Modules\Booking\Application\UseCases\ConfirmBooking;
use App\Modules\Booking\Application\UseCases\RejectBooking;
use App\Modules\Booking\Domain\Exceptions\BookingMustBePending;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/*
 * TOCTOU tests for booking concurrent operations.
 *
 * These tests simulate what happens when two agents (users or processes) race to
 * act on the same pending booking. With lockForUpdate() inside the transaction,
 * the second actor always reads the already-mutated status and throws
 * BookingMustBePending — producing exactly one audit record and one outbox event.
 *
 * The race is simulated by intercepting findForSupplierForUpdate() on the first
 * call and mutating the row in-place before returning, as a stand-in for the
 * competing process that wins the real-world race.
 */

/**
 * Bind a repository proxy that mutates the booking row during the first lock
 * acquisition, simulating the competing process that wins the race.
 */
function bindRaceSimulator(int $bookingId, string $winnerStatus): void
{
    $original = app(BookingRepository::class);
    $intercepted = false;

    app()->bind(BookingRepository::class, function () use ($original, $bookingId, $winnerStatus, &$intercepted) {
        return new class($original, $bookingId, $winnerStatus, $intercepted) implements BookingRepository
        {
            public function __construct(
                private BookingRepository $inner,
                private int $targetId,
                private string $winnerStatus,
                private bool &$intercepted,
            ) {}

            public function findForSupplierForUpdate(int $bookingId, int $supplierId): ?Booking
            {
                if (! $this->intercepted && $bookingId === $this->targetId) {
                    $this->intercepted = true;
                    // Simulate: competing process already committed its transaction.
                    // Mutate the row directly so this actor reads the post-race status.
                    DB::table('bookings')
                        ->where('id', $bookingId)
                        ->update(['status' => $this->winnerStatus, 'updated_at' => now()]);
                }

                return $this->inner->findForSupplierForUpdate($bookingId, $supplierId);
            }

            public function pendingForSupplier(int $supplierId, int $perPage): LengthAwarePaginator
            {
                return $this->inner->pendingForSupplier($supplierId, $perPage);
            }

            public function find(int $bookingId): ?Booking
            {
                return $this->inner->find($bookingId);
            }

            public function findForSupplier(int $bookingId, int $supplierId): ?Booking
            {
                return $this->inner->findForSupplier($bookingId, $supplierId);
            }

            public function updateStatus(Booking $booking, string $status): Booking
            {
                return $this->inner->updateStatus($booking, $status);
            }
        };
    });
}

test('toctou: double confirm produces exactly one confirmation and one outbox event', function () {
    $supplier = Supplier::factory()->create();
    $userA = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $userB = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $booking = Booking::factory()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

    // First request: no race, confirms cleanly.
    $this->actingAs($userA);
    $confirmed = app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $userA->id));

    expect($confirmed->status)->toBe('confirmed');

    // Exactly one audit trail and one outbox event at this point.
    expect(DB::table('booking_actions')
        ->where('booking_id', $booking->id)
        ->where('action', 'confirmed')
        ->count()
    )->toBe(1);

    expect(DB::table('outbox_events')
        ->where('aggregate_id', $booking->id)
        ->where('event_type', 'BookingConfirmed')
        ->count()
    )->toBe(1);

    // Second concurrent request: acquires lock, reads status='confirmed', must throw.
    $this->actingAs($userB);

    app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $userB->id));
})->throws(BookingMustBePending::class);

test('toctou: second confirm does not create duplicate booking_actions or outbox events', function () {
    $supplier = Supplier::factory()->create();
    $userA = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $userB = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $booking = Booking::factory()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

    $this->actingAs($userA);
    app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $userA->id));

    $this->actingAs($userB);
    try {
        app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $userB->id));
    } catch (BookingMustBePending) {
        // Expected — assert no duplicates were written.
        expect(DB::table('booking_actions')
            ->where('booking_id', $booking->id)
            ->where('action', 'confirmed')
            ->count()
        )->toBe(1, 'duplicate booking_actions created');

        expect(DB::table('outbox_events')
            ->where('aggregate_id', $booking->id)
            ->where('event_type', 'BookingConfirmed')
            ->count()
        )->toBe(1, 'duplicate outbox events created');

        return;
    }

    $this->fail('Expected BookingMustBePending on second concurrent confirm.');
});

test('toctou: confirm racing against reject — loser throws BookingMustBePending', function () {
    $supplier = Supplier::factory()->create();
    $userA = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $booking = Booking::factory()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

    $this->actingAs($userA);

    // Simulate: reject wins the race and commits before confirm acquires the lock.
    bindRaceSimulator($booking->id, winnerStatus: 'rejected');

    // Confirm now reads status='rejected' after the interceptor fires — must throw.
    app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $userA->id));
})->throws(BookingMustBePending::class);

test('toctou: reject racing against confirm — loser throws BookingMustBePending', function () {
    $supplier = Supplier::factory()->create();
    $userA = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $booking = Booking::factory()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

    $this->actingAs($userA);

    // Simulate: confirm wins the race and commits before reject acquires the lock.
    bindRaceSimulator($booking->id, winnerStatus: 'confirmed');

    // Reject now reads status='confirmed' after the interceptor fires — must throw.
    app(RejectBooking::class)->handle(new RejectBookingData($booking->id, $userA->id, 'Duplicate request'));
})->throws(BookingMustBePending::class);

test('toctou: no race condition — sequential confirm followed by reject throws correctly', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $booking = Booking::factory()->create(['supplier_id' => $supplier->id, 'status' => 'pending']);

    $this->actingAs($user);

    app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $user->id));

    app(RejectBooking::class)->handle(new RejectBookingData($booking->id, $user->id, 'Duplicate request'));
})->throws(BookingMustBePending::class);
