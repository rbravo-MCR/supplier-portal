<?php

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/*
 * API stress tests — both supplier-service endpoints.
 *
 * BOOKING VOLUME:       100 sequential POSTs — time budget + query budget per request.
 * BOOKING UPSERT:       50 rounds to the same reservation_code — exactly 1 DB row, last value wins.
 * BOOKING ISOLATION:    5 suppliers × 20 bookings interleaved — zero cross-supplier contamination.
 * UNAUTHENTICATED FLOW: 50 valid requests without token — all created.
 * VALIDATION FLOOD:     30 malformed payloads — all 422, zero rows written.
 * AVAILABILITY BATCH:   Single request carrying 100 items — all stored within time budget.
 * AVAILABILITY UPSERT:  30 rounds to the same availability window — exactly 1 row, last quantity wins.
 * AVAILABILITY WINDOWS: 20 distinct batches × 5 items — all 100 unique rows, no data loss.
 *
 * API_ prefix on all constants prevents collision with BookingStressTest.php globals.
 */

const API_BOOKING_VOLUME = 100;
const API_BOOKING_TIME_BUDGET_SECONDS = 45;
const API_QUERY_BUDGET_PER_BOOKING_POST = 12;
const API_UPSERT_ROUNDS = 50;
const API_UNAUTHENTICATED_FLOW_ROUNDS = 50;
const API_VALIDATION_FLOOD_ROUNDS = 30;
const API_MULTI_SUPPLIER_COUNT = 5;
const API_MULTI_SUPPLIER_BOOKINGS = 20;
const API_AVAILABILITY_ITEMS = 100;
const API_AVAILABILITY_TIME_BUDGET_SECONDS = 15;
const API_AVAILABILITY_UPSERT_ROUNDS = 30;
const API_AVAILABILITY_WINDOW_BATCHES = 20;
const API_AVAILABILITY_ITEMS_PER_BATCH = 5;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stressBookingPayload(string $supplierCode, string $reservationCode, array $overrides = []): array
{
    return array_merge([
        'supplier_code' => $supplierCode,
        'reservation_code' => $reservationCode,
        'customer_name' => 'Stress Customer',
        'vehicle_class' => 'ECAR',
        'pickup_office_code' => 'MEX01',
        'dropoff_office_code' => 'MEX01',
        'pickup_at' => '2026-09-01T10:00:00-06:00',
        'dropoff_at' => '2026-09-05T10:00:00-06:00',
        'total_amount' => 299.99,
        'currency' => 'USD',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stressAvailabilityItem(string $officeCode, string $acrissCode, int $quantity = 10, array $overrides = []): array
{
    return array_merge([
        'office_code' => $officeCode,
        'vehicle_class' => 'ECONOMY',
        'acriss_code' => $acrissCode,
        'available_quantity' => $quantity,
        'valid_from' => '2026-09-01',
        'valid_to' => '2026-09-30',
        'status' => 'available',
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Booking API — Volume throughput
// ---------------------------------------------------------------------------

test('api stress: 100 sequential booking POSTs complete within time and query budget', function () {
    $supplier = Supplier::factory()->create(['code' => 'APIVOL']);

    // Warmup outside measurement window to prime caches/compiled state.
    $this->postJson('/api/supplier-service/bookings', stressBookingPayload('APIVOL', 'WARM-UP'))
        ->assertCreated();

    $sampleIndexes = [1, 50, 99];
    $sampleCounts = [];
    $start = microtime(true);

    foreach (range(1, API_BOOKING_VOLUME) as $i) {
        $isSample = in_array($i, $sampleIndexes, true);

        if ($isSample) {
            DB::flushQueryLog();
            DB::enableQueryLog();
        }

        $this->postJson(
            '/api/supplier-service/bookings',
            stressBookingPayload('APIVOL', "RES-{$i}")
        )->assertCreated();

        if ($isSample) {
            $sampleCounts[$i] = count(DB::getQueryLog());
            DB::disableQueryLog();

            expect($sampleCounts[$i])->toBeLessThanOrEqual(
                API_QUERY_BUDGET_PER_BOOKING_POST,
                "Booking POST #{$i} used {$sampleCounts[$i]} queries — N+1 suspected"
            );
        }
    }

    // Stable query count across samples = no N+1 regression.
    if (count($sampleCounts) >= 2) {
        $max = max($sampleCounts);
        $min = min($sampleCounts);
        expect($max - $min)->toBeLessThanOrEqual(
            2,
            'Query count grew across samples: '.json_encode($sampleCounts).' — N+1 suspected'
        );
    }

    $elapsed = microtime(true) - $start;
    expect($elapsed)->toBeLessThan(
        API_BOOKING_TIME_BUDGET_SECONDS,
        sprintf('%d POSTs took %.2fs — exceeded %ds budget', API_BOOKING_VOLUME, $elapsed, API_BOOKING_TIME_BUDGET_SECONDS)
    );

    // All bookings landed in DB.
    expect(DB::table('bookings')
        ->where('supplier_id', $supplier->id)
        ->where('status', 'pending')
        ->count()
    )->toBe(API_BOOKING_VOLUME + 1); // +1 warmup
});

// ---------------------------------------------------------------------------
// Booking API — Upsert idempotency under load
// ---------------------------------------------------------------------------

test('api stress: 50 upsert rounds to same reservation_code produce exactly 1 booking', function () {
    Supplier::factory()->create(['code' => 'APIUPS']);

    // Round 1: creates the booking.
    $this->postJson('/api/supplier-service/bookings', stressBookingPayload('APIUPS', 'RES-IDEM-001'))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    // Rounds 2–N: update. Last amount must win.
    $lastAmount = null;

    foreach (range(2, API_UPSERT_ROUNDS) as $round) {
        $lastAmount = round(100 + $round * 3.75, 2);

        $this->postJson('/api/supplier-service/bookings', stressBookingPayload('APIUPS', 'RES-IDEM-001', [
            'total_amount' => $lastAmount,
            'customer_name' => "Updated Round {$round}",
        ]))
            ->assertOk()
            ->assertJsonPath('data.reservation_code', 'RES-IDEM-001');
    }

    // Exactly 1 row — no phantom duplicates.
    expect(DB::table('bookings')
        ->where('reservation_code', 'RES-IDEM-001')
        ->count()
    )->toBe(1, 'upsert created duplicate rows');

    // Last write wins.
    expect(DB::table('bookings')
        ->where('reservation_code', 'RES-IDEM-001')
        ->value('customer_name')
    )->toBe('Updated Round '.API_UPSERT_ROUNDS);
});

// ---------------------------------------------------------------------------
// Booking API — Multi-supplier isolation under load
// ---------------------------------------------------------------------------

test('api stress: 5 suppliers × 20 bookings interleaved — zero cross-supplier contamination', function () {
    $suppliers = collect(range(1, API_MULTI_SUPPLIER_COUNT))->map(
        fn ($i) => Supplier::factory()->create(['code' => "SUP{$i}"])
    );

    $violations = [];

    // Interleave bookings across suppliers — simulates concurrent multi-tenant traffic.
    foreach (range(1, API_MULTI_SUPPLIER_BOOKINGS) as $booking) {
        foreach ($suppliers as $supplier) {
            $response = $this->postJson(
                '/api/supplier-service/bookings',
                stressBookingPayload($supplier->code, "RES-{$supplier->code}-{$booking}")
            );

            $response->assertCreated();

            $returnedSupplierCode = $response->json('data.supplier.code');

            if ($returnedSupplierCode !== $supplier->code) {
                $violations[] = "Booking RES-{$supplier->code}-{$booking}: response supplier was {$returnedSupplierCode}, expected {$supplier->code}";
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Cross-supplier contamination in API response:'.PHP_EOL.implode(PHP_EOL, $violations)
    );

    $totalExpected = API_MULTI_SUPPLIER_COUNT * API_MULTI_SUPPLIER_BOOKINGS;

    expect(DB::table('bookings')->count())->toBe($totalExpected);

    // Each supplier's bookings contain only their own reservation codes.
    foreach ($suppliers as $supplier) {
        $foreignBookings = DB::table('bookings')
            ->where('supplier_id', $supplier->id)
            ->whereNotLike('reservation_code', "RES-{$supplier->code}-%")
            ->count();

        expect($foreignBookings)->toBe(
            0,
            "Supplier {$supplier->code} has foreign bookings in DB"
        );
    }
});

// ---------------------------------------------------------------------------
// Booking API — Unauthenticated flow
// ---------------------------------------------------------------------------

test('api stress: 50 valid booking POSTs without token all create rows', function () {
    Supplier::factory()->create(['code' => 'APINOTOKEN']);

    $created = 0;

    foreach (range(1, API_UNAUTHENTICATED_FLOW_ROUNDS) as $i) {
        $this->postJson(
            '/api/supplier-service/bookings',
            stressBookingPayload('APINOTOKEN', "RES-NOTOKEN-{$i}")
        )->assertCreated();

        $created++;
    }

    expect($created)->toBe(API_UNAUTHENTICATED_FLOW_ROUNDS);

    expect(DB::table('bookings')->count())->toBe(API_UNAUTHENTICATED_FLOW_ROUNDS);
});

// ---------------------------------------------------------------------------
// Booking API — Validation flood
// ---------------------------------------------------------------------------

test('api stress: 30 malformed booking POSTs all return 422 and write zero rows', function () {
    Supplier::factory()->create(['code' => 'APIVAL']);

    $badPayloads = [
        // Missing required fields.
        [],
        ['supplier_code' => 'APIVAL'],
        ['supplier_code' => 'APIVAL', 'reservation_code' => 'R1'],
        // Invalid supplier code.
        stressBookingPayload('UNKNOWN', 'R-VAL-X'),
        // dropoff before pickup.
        stressBookingPayload('APIVAL', 'R-VAL-DATE', [
            'pickup_at' => '2026-09-10T10:00:00',
            'dropoff_at' => '2026-09-05T10:00:00',
        ]),
        // Negative amount.
        stressBookingPayload('APIVAL', 'R-VAL-AMT', ['total_amount' => -1]),
        // Currency too long.
        stressBookingPayload('APIVAL', 'R-VAL-CUR', ['currency' => 'USDX']),
    ];

    // Pad up to API_VALIDATION_FLOOD_ROUNDS using the first bad payload.
    while (count($badPayloads) < API_VALIDATION_FLOOD_ROUNDS) {
        $badPayloads[] = $badPayloads[0];
    }

    $unprocessable = 0;

    foreach (array_slice($badPayloads, 0, API_VALIDATION_FLOOD_ROUNDS) as $payload) {
        $this->postJson('/api/supplier-service/bookings', $payload)
            ->assertUnprocessable();
        $unprocessable++;
    }

    expect($unprocessable)->toBe(API_VALIDATION_FLOOD_ROUNDS);

    expect(DB::table('bookings')->count())->toBe(0, 'validation-failed requests wrote rows to DB');
});

// ---------------------------------------------------------------------------
// Availability API — Batch throughput (100 items in single request)
// ---------------------------------------------------------------------------

test('api stress: single availability POST with 100 items stores all within time budget', function () {
    Supplier::factory()->create(['code' => 'AVIBATCH']);

    $items = collect(range(1, API_AVAILABILITY_ITEMS))->map(function (int $i): array {
        $acriss = sprintf('%04s', strtoupper(base_convert($i, 10, 36)));

        return stressAvailabilityItem(
            officeCode: sprintf('OFF%02d', ($i % 20) + 1),
            acrissCode: str_pad(strtoupper(base_convert($i, 10, 36)), 4, 'X'),
            quantity: $i,
            overrides: [
                'valid_from' => '2026-09-01',
                'valid_to' => '2026-09-30',
            ]
        );
    })->toArray();

    $start = microtime(true);

    $this->postJson('/api/supplier-service/vehicle-availability', [
        'supplier_code' => 'AVIBATCH',
        'items' => $items,
    ])
        ->assertOk()
        ->assertJsonPath('meta.received', API_AVAILABILITY_ITEMS)
        ->assertJsonPath('meta.stored', API_AVAILABILITY_ITEMS);

    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(
        API_AVAILABILITY_TIME_BUDGET_SECONDS,
        sprintf('%d items took %.2fs — exceeded %ds budget', API_AVAILABILITY_ITEMS, $elapsed, API_AVAILABILITY_TIME_BUDGET_SECONDS)
    );

    expect(DB::table('vehicle_availabilities')->count())->toBe(API_AVAILABILITY_ITEMS);
});

// ---------------------------------------------------------------------------
// Availability API — Upsert idempotency flood
// ---------------------------------------------------------------------------

test('api stress: 30 upsert rounds to same availability window produce exactly 1 row', function () {
    Supplier::factory()->create(['code' => 'AVIUPS']);

    $lastQuantity = null;

    foreach (range(1, API_AVAILABILITY_UPSERT_ROUNDS) as $round) {
        $lastQuantity = $round * 3;

        $this->postJson('/api/supplier-service/vehicle-availability', [
            'supplier_code' => 'AVIUPS',
            'items' => [
                stressAvailabilityItem('MEX01', 'ECAR', $lastQuantity, [
                    'valid_from' => '2026-09-01',
                    'valid_to' => '2026-09-30',
                ]),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('meta.stored', 1);
    }

    // Exactly 1 row — no phantom duplicates.
    expect(DB::table('vehicle_availabilities')
        ->where('location_code', 'MEX01')
        ->where('acriss_code', 'ECAR')
        ->count()
    )->toBe(1, 'upsert created duplicate availability rows');

    // Last write wins.
    expect(DB::table('vehicle_availabilities')
        ->where('location_code', 'MEX01')
        ->where('acriss_code', 'ECAR')
        ->value('available_quantity')
    )->toBe($lastQuantity, 'last quantity not persisted — possible lost update');
});

// ---------------------------------------------------------------------------
// Availability API — Multi-window batch (20 batches × 5 items = 100 unique windows)
// ---------------------------------------------------------------------------

test('api stress: 20 distinct availability batches — all 100 unique windows stored, no data loss', function () {
    Supplier::factory()->create(['code' => 'AVIWIN']);

    $acrissCodes = ['ECAR', 'CCAR', 'ICAR', 'SCAR', 'MVAR'];
    $stored = 0;

    foreach (range(1, API_AVAILABILITY_WINDOW_BATCHES) as $batch) {
        $items = collect($acrissCodes)->map(fn ($acriss) => stressAvailabilityItem(
            officeCode: sprintf('OFF%02d', $batch),
            acrissCode: $acriss,
            quantity: $batch * 2,
            overrides: [
                'valid_from' => now()->addDays($batch)->toDateString(),
                'valid_to' => now()->addDays($batch + 3)->toDateString(),
            ]
        ))->toArray();

        $response = $this->postJson('/api/supplier-service/vehicle-availability', [
            'supplier_code' => 'AVIWIN',
            'items' => $items,
        ])
            ->assertOk();

        $stored += $response->json('meta.stored');
    }

    $totalExpected = API_AVAILABILITY_WINDOW_BATCHES * API_AVAILABILITY_ITEMS_PER_BATCH;

    expect($stored)->toBe($totalExpected, "Expected {$totalExpected} stored across all batches, got {$stored}");

    expect(DB::table('vehicle_availabilities')->count())->toBe(
        $totalExpected,
        'DB row count does not match expected unique windows'
    );

    // Each batch's office code has exactly 5 rows (one per acriss code).
    foreach (range(1, API_AVAILABILITY_WINDOW_BATCHES) as $batch) {
        $officeCode = sprintf('OFF%02d', $batch);
        expect(DB::table('vehicle_availabilities')
            ->where('office_code', $officeCode)
            ->count()
        )->toBe(API_AVAILABILITY_ITEMS_PER_BATCH, "Office {$officeCode} has wrong row count");
    }
});
