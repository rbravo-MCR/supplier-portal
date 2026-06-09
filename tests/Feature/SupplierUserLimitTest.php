<?php

use App\Models\Supplier;
use App\Models\User;
use App\Modules\Supplier\Application\UseCases\CanCreateSupplierUser;

test('supplier can add a user while under the configured limit', function () {
    config(['supplier.max_users' => 3]);

    $supplier = Supplier::factory()->create();
    User::factory()->count(2)->create([
        'supplier_id' => $supplier->id,
        'status' => 'active',
    ]);

    expect(app(CanCreateSupplierUser::class)->handle($supplier))->toBeTrue();
});

test('supplier cannot exceed the configured user limit', function () {
    config(['supplier.max_users' => 3]);

    $supplier = Supplier::factory()->create();
    User::factory()->count(3)->create([
        'supplier_id' => $supplier->id,
        'status' => 'active',
    ]);

    expect(app(CanCreateSupplierUser::class)->handle($supplier))->toBeFalse();
});

test('supplier max users overrides the global limit', function () {
    config(['supplier.max_users' => 3]);

    $supplier = Supplier::factory()->create(['max_users' => 5]);
    User::factory()->count(4)->create([
        'supplier_id' => $supplier->id,
        'status' => 'active',
    ]);

    expect(app(CanCreateSupplierUser::class)->handle($supplier))->toBeTrue();
});

test('pending activation and pending two factor setup count against the limit', function () {
    config(['supplier.max_users' => 3]);

    $supplier = Supplier::factory()->create();

    foreach (['active', 'pending_activation', 'pending_2fa_setup'] as $status) {
        User::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => $status,
        ]);
    }

    expect(app(CanCreateSupplierUser::class)->handle($supplier))->toBeFalse();
});

test('disabled and blocked users do not count against the limit', function () {
    config(['supplier.max_users' => 3]);

    $supplier = Supplier::factory()->create();

    foreach (['active', 'disabled', 'blocked'] as $status) {
        User::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => $status,
        ]);
    }

    expect(app(CanCreateSupplierUser::class)->handle($supplier))->toBeTrue();
});
