<?php

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Booking\Application\DTOs\ConfirmBookingData;
use App\Modules\Booking\Application\DTOs\RejectBookingData;
use App\Modules\Booking\Application\UseCases\ConfirmBooking;
use App\Modules\Booking\Application\UseCases\RejectBooking;
use App\Modules\Booking\Domain\Exceptions\BookingMustBePending;
use App\Modules\Pricing\Application\DTOs\PublishRateData;
use App\Modules\Pricing\Application\UseCases\PublishRate;
use App\Modules\Pricing\Domain\Exceptions\RateOverlapDetected;
use Illuminate\Support\Facades\DB;

/*
 * Stress tests — data integrity and performance under high load.
 *
 * VOLUME:      exercises bulk operations to catch N+1 queries and memory leaks.
 * TOCTOU×N:    100 back-to-back race simulations — verifies the lock invariant holds
 *              under rapid iteration with no state leakage between rounds.
 * ISOLATION:   multi-supplier bulk processing — asserts zero cross-supplier contamination.
 * RATE FLOOD:  rapid-fire publishes against the same rate key — exactly one survives.
 *
 * Query budget per operation is asserted explicitly to catch regressions that
 * silently balloon DB round-trips.
 */

const VOLUME_COUNT = 100;
const VOLUME_TIME_BUDGET_SECONDS = 30;
const QUERY_BUDGET_PER_CONFIRM = 15; // includes SAVEPOINT/RELEASE for SQLite nested transactions
const TOCTOU_ROUNDS = 100;
const SUPPLIER_COUNT = 5;
const BOOKINGS_PER_SUPPLIER = 20;
const RATE_FLOOD_ATTEMPTS = 30;

// ---------------------------------------------------------------------------
// Stress 1 — Volume throughput
// ---------------------------------------------------------------------------

test('stress: bulk confirm throughput — 100 bookings within time and query budget', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $bookings = Booking::factory()->count(VOLUME_COUNT)->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $useCase = app(ConfirmBooking::class);

    // Warm up first booking outside measurement window.
    $useCase->handle(new ConfirmBookingData($bookings->first()->id, $user->id));

    // Measure query count at 3 sample points across the remaining bookings.
    // If count grows from sample to sample, an N+1 regression was introduced.
    $sampleIndexes = [1, 50, 99];
    $sampleCounts = [];

    $start = microtime(true);

    foreach ($bookings->skip(1) as $index => $booking) {
        $isSample = in_array($index, $sampleIndexes, true);

        if ($isSample) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        $useCase->handle(new ConfirmBookingData($booking->id, $user->id));

        if ($isSample) {
            $sampleCounts[$index] = count(DB::getQueryLog());
            DB::disableQueryLog();

            expect($sampleCounts[$index])->toBeLessThanOrEqual(
                QUERY_BUDGET_PER_CONFIRM,
                "Sample booking at index #{$index} used {$sampleCounts[$index]} queries — N+1 suspected"
            );
        }
    }

    // Assert query count is stable across samples (no growth = no N+1).
    if (count($sampleCounts) >= 2) {
        $min = min($sampleCounts);
        $max = max($sampleCounts);
        expect($max - $min)->toBeLessThanOrEqual(
            2,
            'Query count grew across samples: '.json_encode($sampleCounts).' — N+1 suspected'
        );
    }

    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(
        VOLUME_TIME_BUDGET_SECONDS,
        sprintf('%d confirms took %.2fs — exceeded %ds budget', VOLUME_COUNT, $elapsed, VOLUME_TIME_BUDGET_SECONDS)
    );

    // Assert full data integrity after bulk run.
    expect(DB::table('bookings')
        ->where('supplier_id', $supplier->id)
        ->where('status', 'confirmed')
        ->count()
    )->toBe(VOLUME_COUNT);

    expect(DB::table('booking_actions')
        ->whereIn('booking_id', $bookings->pluck('id'))
        ->where('action', 'confirmed')
        ->count()
    )->toBe(VOLUME_COUNT);

    expect(DB::table('outbox_events')
        ->whereIn('aggregate_id', $bookings->pluck('id'))
        ->where('event_type', 'BookingConfirmed')
        ->count()
    )->toBe(VOLUME_COUNT);
});

test('stress: bulk reject throughput — 100 bookings within time and query budget', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $bookings = Booking::factory()->count(VOLUME_COUNT)->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $useCase = app(RejectBooking::class);

    $useCase->handle(new RejectBookingData($bookings->first()->id, $user->id, 'Warmup rejection'));

    $sampleIndexes = [1, 50, 99];
    $sampleCounts = [];

    $start = microtime(true);

    foreach ($bookings->skip(1) as $index => $booking) {
        $isSample = in_array($index, $sampleIndexes, true);

        if ($isSample) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        $useCase->handle(new RejectBookingData($booking->id, $user->id, 'Stress test rejection'));

        if ($isSample) {
            $sampleCounts[$index] = count(DB::getQueryLog());
            DB::disableQueryLog();

            expect($sampleCounts[$index])->toBeLessThanOrEqual(QUERY_BUDGET_PER_CONFIRM);
        }
    }

    if (count($sampleCounts) >= 2) {
        $max = max($sampleCounts);
        $min = min($sampleCounts);
        expect($max - $min)->toBeLessThanOrEqual(
            2,
            'Query count grew across samples: '.json_encode($sampleCounts)
        );
    }

    expect(microtime(true) - $start)->toBeLessThan(VOLUME_TIME_BUDGET_SECONDS);

    expect(DB::table('bookings')
        ->where('supplier_id', $supplier->id)
        ->where('status', 'rejected')
        ->count()
    )->toBe(VOLUME_COUNT);

    expect(DB::table('outbox_events')
        ->whereIn('aggregate_id', $bookings->pluck('id'))
        ->where('event_type', 'BookingRejected')
        ->count()
    )->toBe(VOLUME_COUNT);
});

// ---------------------------------------------------------------------------
// Stress 2 — TOCTOU invariant at scale (100 rounds, zero violations)
// ---------------------------------------------------------------------------

test('stress: toctou invariant holds across 100 rapid race rounds — zero integrity violations', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create(['role' => 'supplier_reservations', 'supplier_id' => $supplier->id]);
    $bookings = Booking::factory()->count(TOCTOU_ROUNDS)->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $confirmUseCase = app(ConfirmBooking::class);
    $violations = [];

    foreach ($bookings as $index => $booking) {
        // Round 1: confirm legitimately.
        $confirmUseCase->handle(new ConfirmBookingData($booking->id, $user->id));

        // Round 2: simulate concurrent second actor — must always throw.
        try {
            $confirmUseCase->handle(new ConfirmBookingData($booking->id, $user->id));
            $violations[] = "booking #{$booking->id} (round {$index}): second confirm did not throw";
        } catch (BookingMustBePending) {
            // Expected — invariant holds.
        }

        // Assert exactly one booking_action and one outbox_event per booking.
        $actionCount = DB::table('booking_actions')
            ->where('booking_id', $booking->id)
            ->where('action', 'confirmed')
            ->count();

        $outboxCount = DB::table('outbox_events')
            ->where('aggregate_id', $booking->id)
            ->where('event_type', 'BookingConfirmed')
            ->count();

        if ($actionCount !== 1) {
            $violations[] = "booking #{$booking->id}: {$actionCount} booking_actions (expected 1)";
        }

        if ($outboxCount !== 1) {
            $violations[] = "booking #{$booking->id}: {$outboxCount} outbox_events (expected 1)";
        }
    }

    expect($violations)->toBeEmpty(
        'Data integrity violations found:'.PHP_EOL.implode(PHP_EOL, $violations)
    );
});

// ---------------------------------------------------------------------------
// Stress 3 — Multi-supplier isolation under bulk load
// ---------------------------------------------------------------------------

test('stress: multi-supplier bulk processing — zero cross-supplier contamination', function () {
    $suppliers = Supplier::factory()->count(SUPPLIER_COUNT)->create();

    $supplierUsers = $suppliers->mapWithKeys(fn ($supplier) => [
        $supplier->id => User::factory()->create([
            'role' => 'supplier_reservations',
            'supplier_id' => $supplier->id,
        ]),
    ]);

    $supplierBookings = $suppliers->mapWithKeys(fn ($supplier) => [
        $supplier->id => Booking::factory()->count(BOOKINGS_PER_SUPPLIER)->create([
            'supplier_id' => $supplier->id,
            'status' => 'pending',
        ]),
    ]);

    $confirmUseCase = app(ConfirmBooking::class);
    $violations = [];

    // Process all bookings interleaved across suppliers — simulates concurrent load.
    for ($i = 0; $i < BOOKINGS_PER_SUPPLIER; $i++) {
        foreach ($suppliers as $supplier) {
            $booking = $supplierBookings[$supplier->id][$i];
            $user = $supplierUsers[$supplier->id];

            $this->actingAs($user);
            $confirmed = $confirmUseCase->handle(new ConfirmBookingData($booking->id, $user->id));

            // Confirmed booking must belong to the acting user's supplier.
            if ($confirmed->supplier_id !== $supplier->id) {
                $violations[] = "booking #{$booking->id} confirmed under supplier #{$confirmed->supplier_id}, expected #{$supplier->id}";
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Cross-supplier contamination detected:'.PHP_EOL.implode(PHP_EOL, $violations)
    );

    $totalExpected = SUPPLIER_COUNT * BOOKINGS_PER_SUPPLIER;

    expect(DB::table('booking_actions')->where('action', 'confirmed')->count())
        ->toBe($totalExpected);

    // Each supplier's audit log must contain only their own bookings.
    foreach ($suppliers as $supplier) {
        $supplierBookingIds = $supplierBookings[$supplier->id]->pluck('id');

        $foreignActions = DB::table('booking_actions')
            ->where('supplier_id', $supplier->id)
            ->whereNotIn('booking_id', $supplierBookingIds)
            ->count();

        expect($foreignActions)->toBe(
            0,
            "supplier #{$supplier->id} has booking_actions for foreign bookings"
        );
    }
});

// ---------------------------------------------------------------------------
// Stress 4 — Rate overlap flood: N attempts, exactly 1 wins
// ---------------------------------------------------------------------------

test('stress: rate publish flood — 30 attempts on same key produce exactly one active rate', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create(['role' => 'supplier_pricing', 'supplier_id' => $supplier->id]);

    $this->actingAs($user);

    $useCase = app(PublishRate::class);
    $rateData = new PublishRateData(
        officeCode: 'CUN',
        vehicleClass: 'Economy',
        acrissCode: 'ECAR',
        ratePlanCode: 'STD',
        currency: 'USD',
        basePrice: 89.99,
        validFrom: now()->toDateString(),
        validTo: now()->addDays(30)->toDateString(),
        minDays: null,
        maxDays: null,
        createdBy: $user->id,
    );

    $successes = 0;
    $overlapRejections = 0;
    $unexpectedErrors = [];

    for ($attempt = 1; $attempt <= RATE_FLOOD_ATTEMPTS; $attempt++) {
        try {
            $useCase->handle($rateData);
            $successes++;
        } catch (RateOverlapDetected) {
            $overlapRejections++;
        } catch (Throwable $e) {
            $unexpectedErrors[] = "attempt #{$attempt}: ".get_class($e).' — '.$e->getMessage();
        }
    }

    expect($unexpectedErrors)->toBeEmpty(
        'Unexpected errors during rate flood:'.PHP_EOL.implode(PHP_EOL, $unexpectedErrors)
    );

    expect($successes)->toBe(1, "{$successes} rate publishes succeeded — expected exactly 1");
    expect($overlapRejections)->toBe(
        RATE_FLOOD_ATTEMPTS - 1,
        'Expected '.(RATE_FLOOD_ATTEMPTS - 1)." overlap rejections, got {$overlapRejections}"
    );

    // Exactly one active rate in DB for this key.
    expect(DB::table('rates')
        ->where('supplier_id', $supplier->id)
        ->where('office_code', 'CUN')
        ->where('acriss_code', 'ECAR')
        ->where('rate_plan_code', 'STD')
        ->where('status', 'active')
        ->count()
    )->toBe(1);
});
