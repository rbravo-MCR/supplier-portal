<?php

use App\Models\Booking;
use App\Models\OutboxEvent;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Booking\Application\DTOs\ConfirmBookingData;
use App\Modules\Booking\Application\DTOs\RejectBookingData;
use App\Modules\Booking\Application\UseCases\ConfirmBooking;
use App\Modules\Booking\Application\UseCases\RejectBooking;
use App\Modules\Import\Application\DTOs\CreateRateImportData;
use App\Modules\Import\Application\UseCases\CreateRateImport;
use App\Modules\Pricing\Application\DTOs\PublishRateData;
use App\Modules\Pricing\Application\UseCases\PublishRate;

test('booking confirmed event follows catalog payload', function () {
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

    app(ConfirmBooking::class)->handle(new ConfirmBookingData($booking->id, $user->id));

    $event = OutboxEvent::query()->where('event_type', 'BookingConfirmed')->firstOrFail();

    expect($event->uuid)->not->toBeEmpty()
        ->and($event->payload)->toHaveKeys(['booking_uuid', 'supplier_id', 'user_id', 'confirmed_at', 'occurred_at'])
        ->and($event->payload['booking_uuid'])->toBe($booking->uuid)
        ->and($event->payload['supplier_id'])->toBe($supplier->id)
        ->and($event->payload['user_id'])->toBe($user->id)
        ->and($event->payload['confirmed_at'])->not->toBeEmpty()
        ->and($event->payload['occurred_at'])->not->toBeEmpty();
});

test('booking rejected event follows catalog payload', function () {
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

    app(RejectBooking::class)->handle(new RejectBookingData($booking->id, $user->id, 'No availability'));

    $event = OutboxEvent::query()->where('event_type', 'BookingRejected')->firstOrFail();

    expect($event->uuid)->not->toBeEmpty()
        ->and($event->payload)->toHaveKeys(['booking_uuid', 'supplier_id', 'user_id', 'reason', 'rejected_at', 'occurred_at'])
        ->and($event->payload['booking_uuid'])->toBe($booking->uuid)
        ->and($event->payload['reason'])->toBe('No availability')
        ->and($event->payload['rejected_at'])->not->toBeEmpty();
});

test('rate import uploaded event follows catalog payload', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);

    $rateImport = app(CreateRateImport::class)->handle(new CreateRateImportData(
        originalFilename: 'rates.xlsx',
        storedPath: 'imports/rates.xlsx',
        uploadedBy: $user->id,
        rows: [['office_code' => 'CUN']],
    ));

    $event = OutboxEvent::query()->where('event_type', 'RateImportUploaded')->firstOrFail();

    expect($event->uuid)->not->toBeEmpty()
        ->and($event->payload)->toHaveKeys(['rate_import_uuid', 'supplier_id', 'uploaded_by', 'occurred_at'])
        ->and($event->payload['rate_import_uuid'])->toBe($rateImport->uuid)
        ->and($event->payload['supplier_id'])->toBe($supplier->id)
        ->and($event->payload['uploaded_by'])->toBe($user->id);
});

test('rates published event follows catalog payload', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);

    $rate = app(PublishRate::class)->handle(new PublishRateData(
        officeCode: 'CUN',
        vehicleClass: 'SUV',
        acrissCode: 'IFAR',
        ratePlanCode: 'STD',
        currency: 'USD',
        basePrice: 125.50,
        validFrom: '2026-07-01',
        validTo: '2026-07-10',
        minDays: 1,
        maxDays: 14,
        createdBy: $user->id,
    ));

    $event = OutboxEvent::query()->where('event_type', 'RatesPublished')->firstOrFail();

    expect($event->uuid)->not->toBeEmpty()
        ->and($event->payload)->toHaveKeys(['supplier_id', 'rate_plan_code', 'version', 'occurred_at'])
        ->and($event->payload['supplier_id'])->toBe($supplier->id)
        ->and($event->payload['rate_plan_code'])->toBe('STD')
        ->and($event->payload['version'])->toBe($rate->version);
});
