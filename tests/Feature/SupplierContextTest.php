<?php

use App\Models\Supplier;
use App\Models\User;
use App\Shared\Exceptions\SupplierContextUnavailable;
use App\Shared\Support\SupplierContext;

test('it resolves the supplier from the authenticated user', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($user);

    expect(app(SupplierContext::class)->current()->is($supplier))->toBeTrue()
        ->and(app(SupplierContext::class)->id())->toBe($supplier->id);
});

test('it ignores supplier id provided by the request', function () {
    $currentSupplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create(['supplier_id' => $currentSupplier->id]);

    $this->actingAs($user);
    request()->merge(['supplier_id' => $otherSupplier->id]);

    expect(app(SupplierContext::class)->id())->toBe($currentSupplier->id);
});

test('it fails closed when the authenticated user has no supplier', function () {
    $user = User::factory()->create(['supplier_id' => null]);

    $this->actingAs($user);

    app(SupplierContext::class)->current();
})->throws(SupplierContextUnavailable::class);

test('it fails closed for guests', function () {
    app(SupplierContext::class)->current();
})->throws(SupplierContextUnavailable::class);

test('it detects whether a user belongs to the current supplier', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $currentUser = User::factory()->create(['supplier_id' => $supplier->id]);
    $sameSupplierUser = User::factory()->create(['supplier_id' => $supplier->id]);
    $otherSupplierUser = User::factory()->create(['supplier_id' => $otherSupplier->id]);

    $this->actingAs($currentUser);

    $context = app(SupplierContext::class);

    expect($context->allows($sameSupplierUser))->toBeTrue()
        ->and($context->allows($otherSupplierUser))->toBeFalse();
});
