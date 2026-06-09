<?php

use App\Models\Rate;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Pricing\Application\DTOs\PublishRateData;
use App\Modules\Pricing\Application\UseCases\PublishRate;
use App\Modules\Pricing\Domain\Exceptions\RateOverlapDetected;

test('supplier can publish a valid rate using authenticated supplier context', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);
    request()->merge(['supplier_id' => $otherSupplier->id]);

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

    expect($rate->supplier_id)->toBe($supplier->id)
        ->and($rate->version)->toBe(1)
        ->and($rate->status)->toBe('active');

    $this->assertDatabaseMissing('rates', [
        'supplier_id' => $otherSupplier->id,
        'office_code' => 'CUN',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
        'module' => 'pricing',
        'action' => 'rate_published',
        'entity_type' => Rate::class,
        'entity_id' => $rate->id,
    ]);
});

test('rate validity must not overlap for the same supplier and rate key', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Rate::factory()->create([
        'supplier_id' => $supplier->id,
        'office_code' => 'CUN',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-15',
        'status' => 'active',
    ]);

    $this->actingAs($user);

    app(PublishRate::class)->handle(new PublishRateData(
        officeCode: 'CUN',
        vehicleClass: 'SUV',
        acrissCode: 'IFAR',
        ratePlanCode: 'STD',
        currency: 'USD',
        basePrice: 140,
        validFrom: '2026-07-10',
        validTo: '2026-07-20',
        minDays: null,
        maxDays: null,
        createdBy: $user->id,
    ));
})->throws(RateOverlapDetected::class);

test('rate history is not overwritten when publishing a later non overlapping version', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $existing = Rate::factory()->create([
        'supplier_id' => $supplier->id,
        'office_code' => 'CUN',
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-15',
        'status' => 'active',
        'version' => 1,
    ]);

    $this->actingAs($user);

    $newRate = app(PublishRate::class)->handle(new PublishRateData(
        officeCode: 'CUN',
        vehicleClass: 'SUV',
        acrissCode: 'IFAR',
        ratePlanCode: 'STD',
        currency: 'USD',
        basePrice: 155,
        validFrom: '2026-07-16',
        validTo: '2026-07-31',
        minDays: null,
        maxDays: null,
        createdBy: $user->id,
    ));

    expect($existing->fresh()->version)->toBe(1)
        ->and($existing->fresh()->base_price)->toEqual('100.00')
        ->and($newRate->version)->toBe(2);

    expect(Rate::query()->where('supplier_id', $supplier->id)->count())->toBe(2);
});
