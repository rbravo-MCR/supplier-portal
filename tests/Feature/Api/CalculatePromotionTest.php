<?php

use App\Models\Office;
use App\Models\Promotion;
use App\Models\Supplier;

beforeEach(function () {
    $this->supplier = Supplier::factory()->create(['code' => 'TEST']);
});

test('api returns no promotions when none exist', function () {
    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-05T10:00:00Z',
        'base_amount' => 850.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJson([
            'original_amount' => 850.00,
            'applicable_promotions' => [],
            'total_discount' => 0.0,
            'final_amount' => 850.00,
            'currency' => 'USD',
            'rental_days' => 4,
        ]);
});

test('api applies seasonal promotion', function () {
    Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Verano 2026',
        'type' => 'seasonal',
        'discount_type' => 'percentage',
        'discount_value' => 15.00,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-10T10:00:00Z',
        'dropoff_at' => '2026-07-15T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('original_amount', 1000)
        ->assertJsonPath('total_discount', 150)
        ->assertJsonPath('final_amount', 850)
        ->assertJsonPath('applicable_promotions.0.type', 'seasonal')
        ->assertJsonPath('applicable_promotions.0.discount_percentage', 15);
});

test('api applies volume promotion with tiers', function () {
    $promotion = Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Descuento por volumen',
        'type' => 'volume',
        'discount_type' => 'percentage',
        'discount_value' => 5.00,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $promotion->tiers()->create([
        'min_days' => 7,
        'max_days' => 13,
        'discount_value' => 10.00,
    ]);

    $promotion->tiers()->create([
        'min_days' => 14,
        'max_days' => null,
        'discount_value' => 15.00,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-10T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('original_amount', 1000)
        ->assertJsonPath('total_discount', 100)
        ->assertJsonPath('final_amount', 900)
        ->assertJsonPath('applicable_promotions.0.type', 'volume')
        ->assertJsonPath('applicable_promotions.0.discount_percentage', 10)
        ->assertJsonPath('applicable_promotions.0.tier.min_days', 7);
});

test('api applies free days promotion when rental meets minimum days', function () {
    Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Renta 4 paga 3',
        'type' => 'seasonal',
        'discount_type' => 'free_days',
        'discount_value' => 0,
        'min_rental_days' => 4,
        'free_days' => 1,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-05T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('total_discount', 250)
        ->assertJsonPath('final_amount', 750)
        ->assertJsonPath('applicable_promotions.0.type', 'free_days')
        ->assertJsonPath('applicable_promotions.0.free_days_granted', 1)
        ->assertJsonPath('applicable_promotions.0.paid_days', 3);
});

test('api applies daily rate promotion by rental day ranges', function () {
    $promotion = Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Precio por estancia',
        'type' => 'seasonal',
        'discount_type' => 'daily_rate',
        'discount_value' => 0,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $promotion->tiers()->createMany([
        ['min_days' => 1, 'max_days' => 4, 'discount_value' => 620.00],
        ['min_days' => 5, 'max_days' => 6, 'discount_value' => 500.00],
        ['min_days' => 7, 'max_days' => null, 'discount_value' => 480.00],
    ]);

    $fiveDayResponse = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-06T10:00:00Z',
        'base_amount' => 3100.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $fiveDayResponse->assertOk()
        ->assertJsonPath('rental_days', 5)
        ->assertJsonPath('total_discount', 600)
        ->assertJsonPath('final_amount', 2500)
        ->assertJsonPath('applicable_promotions.0.tier.daily_rate', 500);

    $sevenDayResponse = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-08T10:00:00Z',
        'base_amount' => 4340.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $sevenDayResponse->assertOk()
        ->assertJsonPath('rental_days', 7)
        ->assertJsonPath('total_discount', 980)
        ->assertJsonPath('final_amount', 3360)
        ->assertJsonPath('applicable_promotions.0.tier.daily_rate', 480);
});

test('api applies volume promotion only when vehicle count reaches minimum', function () {
    Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Dos vehiculos',
        'type' => 'volume',
        'discount_type' => 'fixed_amount',
        'discount_value' => 150.00,
        'min_vehicle_count' => 2,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $basePayload = [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-05T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
    ];

    $this->postJson(route('api.promotions.calculate'), $basePayload + ['vehicle_count' => 1])
        ->assertOk()
        ->assertJsonPath('total_discount', 0);

    $this->postJson(route('api.promotions.calculate'), $basePayload + ['vehicle_count' => 2])
        ->assertOk()
        ->assertJsonPath('total_discount', 150)
        ->assertJsonPath('final_amount', 850);
});

test('api applies both seasonal and volume promotions cumulatively', function () {
    Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Verano 2026',
        'type' => 'seasonal',
        'discount_type' => 'percentage',
        'discount_value' => 10.00,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $volumePromo = Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Volumen',
        'type' => 'volume',
        'discount_type' => 'percentage',
        'discount_value' => 5.00,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $volumePromo->tiers()->create([
        'min_days' => 7,
        'max_days' => null,
        'discount_value' => 10.00,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-10T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('original_amount', 1000)
        ->assertJsonPath('total_discount', 190)
        ->assertJsonPath('final_amount', 810)
        ->assertJsonPath('applicable_promotions', function ($promotions) {
            $types = collect($promotions)->pluck('type')->toArray();

            return in_array('seasonal', $types, true) && in_array('volume', $types, true);
        });
});

test('api does not apply promotion for inactive supplier', function () {
    $inactiveSupplier = Supplier::factory()->create(['code' => 'INACTIVE', 'status' => 'inactive']);

    Promotion::factory()->create([
        'supplier_id' => $inactiveSupplier->id,
        'type' => 'seasonal',
        'discount_type' => 'percentage',
        'discount_value' => 20.00,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'INACTIVE',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-10T10:00:00Z',
        'dropoff_at' => '2026-07-15T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_code']);
});

test('api does not apply promotion when pickup is outside validity', function () {
    Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Verano 2026',
        'type' => 'seasonal',
        'discount_type' => 'percentage',
        'discount_value' => 20.00,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-08-10T10:00:00Z',
        'dropoff_at' => '2026-08-15T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('applicable_promotions', [])
        ->assertJsonPath('total_discount', 0)
        ->assertJsonPath('final_amount', 1000);
});

test('api filters promotions by office when applies_to_all_offices is false', function () {
    $office = Office::factory()->create([
        'supplier_id' => $this->supplier->id,
        'code' => 'CUN01',
        'status' => 'active',
    ]);

    $promotion = Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Oferta CUN',
        'type' => 'seasonal',
        'discount_type' => 'percentage',
        'discount_value' => 20.00,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => false,
        'applies_to_all_categories' => true,
    ]);

    $promotion->offices()->attach($office->id);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-10T10:00:00Z',
        'dropoff_at' => '2026-07-15T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('total_discount', 200)
        ->assertJsonPath('final_amount', 800);

    $responseOtherOffice = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'OTHER',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-10T10:00:00Z',
        'dropoff_at' => '2026-07-15T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $responseOtherOffice->assertOk()
        ->assertJsonPath('total_discount', fn (int|float $totalDiscount): bool => (float) $totalDiscount === 0.0)
        ->assertJsonPath('final_amount', fn (int|float $finalAmount): bool => (float) $finalAmount === 1000.0);
});

test('api validates required fields', function () {
    $response = $this->postJson(route('api.promotions.calculate'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_code', 'office_code', 'acriss_code', 'pickup_at', 'dropoff_at', 'base_amount', 'currency']);
});

test('api validates dropoff_at is after pickup_at', function () {
    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-10T10:00:00Z',
        'dropoff_at' => '2026-07-05T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['dropoff_at']);
});

test('api picks the best seasonal promotion when multiple overlap', function () {
    Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Verano 10%',
        'type' => 'seasonal',
        'discount_type' => 'percentage',
        'discount_value' => 10.00,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Verano 20%',
        'type' => 'seasonal',
        'discount_type' => 'percentage',
        'discount_value' => 20.00,
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-10T10:00:00Z',
        'dropoff_at' => '2026-07-15T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('total_discount', 200)
        ->assertJsonPath('applicable_promotions.0.name', 'Verano 20%');
});

test('api applies vehicle volume promotion with tiers', function () {
    $promotion = Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Descuento por flota',
        'type' => 'vehicle_volume',
        'discount_type' => 'percentage',
        'discount_value' => 0,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $promotion->vehicleTiers()->create([
        'min_vehicles' => 3,
        'max_vehicles' => 5,
        'discount_value' => 10.00,
    ]);

    $promotion->vehicleTiers()->create([
        'min_vehicles' => 6,
        'max_vehicles' => null,
        'discount_value' => 15.00,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-05T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 4,
    ]);

    $response->assertOk()
        ->assertJsonPath('original_amount', 1000)
        ->assertJsonPath('total_discount', 100)
        ->assertJsonPath('final_amount', 900)
        ->assertJsonPath('applicable_promotions.0.type', 'vehicle_volume')
        ->assertJsonPath('applicable_promotions.0.discount_percentage', 10)
        ->assertJsonPath('applicable_promotions.0.tier.min_vehicles', 3);
});

test('api does not apply vehicle volume promotion when vehicle count is below tier', function () {
    $promotion = Promotion::factory()->create([
        'supplier_id' => $this->supplier->id,
        'name' => 'Flota grande',
        'type' => 'vehicle_volume',
        'discount_type' => 'percentage',
        'discount_value' => 0,
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
        'status' => 'active',
        'applies_to_all_offices' => true,
        'applies_to_all_categories' => true,
    ]);

    $promotion->vehicleTiers()->create([
        'min_vehicles' => 3,
        'max_vehicles' => null,
        'discount_value' => 10.00,
    ]);

    $response = $this->postJson(route('api.promotions.calculate'), [
        'supplier_code' => 'TEST',
        'office_code' => 'CUN01',
        'acriss_code' => 'ECAR',
        'pickup_at' => '2026-07-01T10:00:00Z',
        'dropoff_at' => '2026-07-05T10:00:00Z',
        'base_amount' => 1000.00,
        'currency' => 'USD',
        'vehicle_count' => 2,
    ]);

    $response->assertOk()
        ->assertJsonPath('applicable_promotions', [])
        ->assertJsonPath('total_discount', 0)
        ->assertJsonPath('final_amount', 1000);
});
