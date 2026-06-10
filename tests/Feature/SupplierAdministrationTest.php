<?php

use App\Models\Supplier;
use App\Models\User;
use App\Modules\Supplier\Application\DTOs\CreateSupplierData;
use App\Modules\Supplier\Application\UseCases\CreateSupplier;
use App\Modules\Supplier\Domain\Exceptions\SupplierCodeAlreadyExists;
use App\Policies\SupplierPolicy;

test('only platform administrators can create suppliers', function () {
    $policy = new SupplierPolicy;

    expect($policy->create(User::factory()->make(['role' => 'super_admin'])))->toBeTrue()
        ->and($policy->create(User::factory()->make(['role' => 'admin'])))->toBeTrue()
        ->and($policy->create(User::factory()->make(['role' => 'auditor'])))->toBeFalse()
        ->and($policy->create(User::factory()->make(['role' => 'supplier_admin'])))->toBeFalse()
        ->and($policy->create(User::factory()->make(['role' => 'supplier_user'])))->toBeFalse();
});

test('platform roles can view suppliers but supplier roles cannot', function () {
    $policy = new SupplierPolicy;

    expect($policy->viewAny(User::factory()->make(['role' => 'super_admin'])))->toBeTrue()
        ->and($policy->viewAny(User::factory()->make(['role' => 'admin'])))->toBeTrue()
        ->and($policy->viewAny(User::factory()->make(['role' => 'auditor'])))->toBeTrue()
        ->and($policy->viewAny(User::factory()->make(['role' => 'admin', 'supplier_id' => 1])))->toBeFalse()
        ->and($policy->viewAny(User::factory()->make(['role' => 'supplier_admin'])))->toBeFalse()
        ->and($policy->viewAny(User::factory()->make(['role' => 'supplier_user'])))->toBeFalse();
});

test('super admin can create a supplier with configurable limits', function () {
    $actor = User::factory()->create(['role' => 'super_admin', 'supplier_id' => null]);

    $supplier = app(CreateSupplier::class)->handle(
        new CreateSupplierData(
            name: 'Acme Car Rentals',
            code: ' acme ',
            status: 'inactive',
            maxUsers: 8,
            contactName: 'Jane Admin',
            email: 'ops@example.com',
            phone: '+52 555 0100',
        ),
        $actor,
    );

    expect($supplier->uuid)->not->toBeEmpty()
        ->and($supplier->code)->toBe('ACME')
        ->and($supplier->max_users)->toBe(8)
        ->and($supplier->status)->toBe('inactive');

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'name' => 'Acme Car Rentals',
        'code' => 'ACME',
        'status' => 'inactive',
        'max_users' => 8,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'supplier_id' => $supplier->id,
        'user_id' => $actor->id,
        'module' => 'supplier',
        'action' => 'created',
        'entity_type' => Supplier::class,
        'entity_id' => $supplier->id,
    ]);
});

test('supplier code must be unique', function () {
    Supplier::factory()->create(['code' => 'ACME']);

    $actor = User::factory()->create(['role' => 'super_admin', 'supplier_id' => null]);

    app(CreateSupplier::class)->handle(
        new CreateSupplierData(
            name: 'Acme Duplicate',
            code: 'acme',
            status: 'active',
            maxUsers: null,
            contactName: null,
            email: null,
            phone: null,
        ),
        $actor,
    );
})->throws(SupplierCodeAlreadyExists::class);

test('auditors can view but cannot mutate a supplier', function () {
    $policy = new SupplierPolicy;
    $supplier = Supplier::factory()->make();
    $auditor = User::factory()->make(['role' => 'auditor']);

    expect($policy->view($auditor, $supplier))->toBeTrue()
        ->and($policy->update($auditor, $supplier))->toBeFalse()
        ->and($policy->delete($auditor, $supplier))->toBeFalse();
});

test('admin users can update supplier status', function () {
    $policy = new SupplierPolicy;
    $supplier = Supplier::factory()->make();

    expect($policy->update(User::factory()->make(['role' => 'super_admin']), $supplier))->toBeTrue()
        ->and($policy->update(User::factory()->make(['role' => 'admin']), $supplier))->toBeTrue()
        ->and($policy->update(User::factory()->make(['role' => 'supplier_admin']), $supplier))->toBeFalse();
});
