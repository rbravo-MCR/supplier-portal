<?php

use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Promotions\Application\DTOs\CreatePromotionData;
use App\Modules\Promotions\Application\UseCases\CreatePromotion;
use App\Modules\Promotions\Application\UseCases\DeletePromotion;
use App\Modules\Promotions\Application\UseCases\TogglePromotionStatus;
use App\Modules\Promotions\Domain\Exceptions\InvalidPromotionData;
use App\Modules\Promotions\Domain\Exceptions\PromotionOverlapDetected;
use App\Modules\Promotions\Domain\Rules\PromotionDatesMustBeValid;
use App\Modules\Promotions\Domain\Rules\VolumeTierDaysMustBeValid;

test('supplier can create a seasonal promotion', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);

    $promotion = app(CreatePromotion::class)->handle(new CreatePromotionData(
        name: 'Verano 2026',
        type: 'seasonal',
        discountType: 'percentage',
        discountValue: 15.00,
        validFrom: '2026-07-01',
        validTo: '2026-07-31',
        appliesToAllOffices: true,
        appliesToAllCategories: true,
        officeIds: [],
        categoryIds: [],
        tiers: [],
        createdBy: $user->id,
    ));

    expect($promotion->supplier_id)->toBe($supplier->id)
        ->and($promotion->name)->toBe('Verano 2026')
        ->and($promotion->type)->toBe('seasonal')
        ->and($promotion->status)->toBe('active');

    $this->assertDatabaseHas('audit_logs', [
        'supplier_id' => $supplier->id,
        'module' => 'promotions',
        'action' => 'promotion_created',
        'entity_type' => Promotion::class,
        'entity_id' => $promotion->id,
    ]);
});

test('supplier can create a volume promotion with tiers', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);

    $promotion = app(CreatePromotion::class)->handle(new CreatePromotionData(
        name: 'Descuento por volumen',
        type: 'volume',
        discountType: 'percentage',
        discountValue: 5.00,
        validFrom: '2026-01-01',
        validTo: '2026-12-31',
        appliesToAllOffices: true,
        appliesToAllCategories: true,
        officeIds: [],
        categoryIds: [],
        tiers: [
            ['min_days' => 7, 'max_days' => 13, 'discount_value' => 10.00],
            ['min_days' => 14, 'max_days' => null, 'discount_value' => 15.00],
        ],
        createdBy: $user->id,
    ));

    expect($promotion->type)->toBe('volume')
        ->and($promotion->tiers)->toHaveCount(2);

    $this->assertDatabaseHas('promotion_discount_tiers', [
        'promotion_id' => $promotion->id,
        'min_days' => 7,
        'max_days' => 13,
        'discount_value' => '10.00',
    ]);

    $this->assertDatabaseHas('promotion_discount_tiers', [
        'promotion_id' => $promotion->id,
        'min_days' => 14,
        'max_days' => null,
        'discount_value' => '15.00',
    ]);
});

test('seasonal promotions must not overlap for the same supplier', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Promotion::factory()->create([
        'supplier_id' => $supplier->id,
        'type' => 'seasonal',
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
        'status' => 'active',
    ]);

    $this->actingAs($user);

    app(CreatePromotion::class)->handle(new CreatePromotionData(
        name: 'Verano 2',
        type: 'seasonal',
        discountType: 'percentage',
        discountValue: 10.00,
        validFrom: '2026-07-15',
        validTo: '2026-08-15',
        appliesToAllOffices: true,
        appliesToAllCategories: true,
        officeIds: [],
        categoryIds: [],
        tiers: [],
        createdBy: $user->id,
    ));
})->throws(PromotionOverlapDetected::class);

test('promotion dates must be valid', function () {
    $rule = new PromotionDatesMustBeValid;

    expect(fn () => $rule->validate('2026-08-01', '2026-07-01'))
        ->toThrow(InvalidPromotionData::class);

    expect($rule->validate('2026-07-01', '2026-07-01'))->toBeTrue();
    expect($rule->validate('2026-07-01', '2026-08-01'))->toBeTrue();
});

test('volume tier days must be valid', function () {
    $rule = new VolumeTierDaysMustBeValid;

    expect(fn () => $rule->validate([]))
        ->toThrow(InvalidPromotionData::class, 'Volume promotions require at least one discount tier.');

    expect(fn () => $rule->validate([
        ['min_days' => 0, 'max_days' => 5, 'discount_value' => 10],
    ]))->toThrow(InvalidPromotionData::class, 'minimum days must be at least 1');

    expect(fn () => $rule->validate([
        ['min_days' => 7, 'max_days' => 5, 'discount_value' => 10],
    ]))->toThrow(InvalidPromotionData::class, 'maximum days must be greater than or equal to minimum days');

    expect(fn () => $rule->validate([
        ['min_days' => 7, 'max_days' => 13, 'discount_value' => 10],
        ['min_days' => 10, 'max_days' => 20, 'discount_value' => 15],
    ]))->toThrow(InvalidPromotionData::class, 'days overlap with a previous tier');

    expect($rule->validate([
        ['min_days' => 7, 'max_days' => 13, 'discount_value' => 10],
        ['min_days' => 14, 'max_days' => null, 'discount_value' => 15],
    ]))->toBeTrue();
});

test('can toggle promotion status', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $promotion = Promotion::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'active',
    ]);

    $this->actingAs($user);

    $updated = app(TogglePromotionStatus::class)->handle($promotion);

    expect($updated->status)->toBe('inactive');

    $updatedAgain = app(TogglePromotionStatus::class)->handle($updated);

    expect($updatedAgain->status)->toBe('active');
});

test('cannot delete promotion applied to bookings', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $promotion = Promotion::factory()->create([
        'supplier_id' => $supplier->id,
    ]);

    Booking::factory()->create([
        'supplier_id' => $supplier->id,
    ])->promotions()->attach($promotion->id, [
        'original_amount' => 100,
        'discount_amount' => 10,
        'final_amount' => 90,
        'applied_at' => now(),
    ]);

    $this->actingAs($user);

    app(DeletePromotion::class)->handle($promotion);
})->throws(RuntimeException::class, 'Cannot delete a promotion that has been applied to bookings.');

test('promotions page is accessible to supplier users', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.promotions'))
        ->assertOk()
        ->assertSee('Promociones');
});
