<?php

use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\RatePolicy;
use App\Policies\UserPolicy;

test('platform roles can view users and only non auditors can mutate users', function () {
    $policy = new UserPolicy();
    $target = User::factory()->make();

    foreach (['super_admin', 'admin', 'auditor'] as $role) {
        $actor = User::factory()->make(['role' => $role, 'supplier_id' => null]);

        expect($policy->viewAny($actor))->toBeTrue()
            ->and($policy->view($actor, $target))->toBeTrue();
    }

    expect($policy->create(User::factory()->make(['role' => 'super_admin'])))->toBeTrue()
        ->and($policy->create(User::factory()->make(['role' => 'admin'])))->toBeTrue()
        ->and($policy->create(User::factory()->make(['role' => 'auditor'])))->toBeFalse()
        ->and($policy->update(User::factory()->make(['role' => 'admin']), $target))->toBeTrue()
        ->and($policy->disable(User::factory()->make(['role' => 'admin']), $target))->toBeTrue()
        ->and($policy->update(User::factory()->make(['role' => 'auditor']), $target))->toBeFalse()
        ->and($policy->disable(User::factory()->make(['role' => 'auditor']), $target))->toBeFalse();
});

test('supplier admin can manage only users from own supplier', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $policy = new UserPolicy();
    $actor = User::factory()->make([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $ownUser = User::factory()->make(['supplier_id' => $supplier->id]);
    $otherUser = User::factory()->make(['supplier_id' => $otherSupplier->id]);

    expect($policy->viewAny($actor))->toBeTrue()
        ->and($policy->view($actor, $ownUser))->toBeTrue()
        ->and($policy->view($actor, $otherUser))->toBeFalse()
        ->and($policy->create($actor))->toBeTrue()
        ->and($policy->update($actor, $ownUser))->toBeTrue()
        ->and($policy->disable($actor, $ownUser))->toBeTrue()
        ->and($policy->update($actor, $otherUser))->toBeFalse()
        ->and($policy->disable($actor, $otherUser))->toBeFalse();
});

test('supplier user cannot manage users', function () {
    $supplier = Supplier::factory()->create();
    $policy = new UserPolicy();
    $actor = User::factory()->make([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);
    $target = User::factory()->make(['supplier_id' => $supplier->id]);

    expect($policy->viewAny($actor))->toBeFalse()
        ->and($policy->view($actor, $target))->toBeFalse()
        ->and($policy->create($actor))->toBeFalse()
        ->and($policy->update($actor, $target))->toBeFalse()
        ->and($policy->disable($actor, $target))->toBeFalse();
});

test('rate import excel follows permission matrix', function () {
    $policy = new RatePolicy();

    expect($policy->importExcel(User::factory()->make(['role' => 'super_admin'])))->toBeTrue()
        ->and($policy->importExcel(User::factory()->make(['role' => 'admin'])))->toBeTrue()
        ->and($policy->importExcel(User::factory()->make(['role' => 'supplier_admin'])))->toBeTrue()
        ->and($policy->importExcel(User::factory()->make(['role' => 'auditor'])))->toBeFalse()
        ->and($policy->importExcel(User::factory()->make(['role' => 'supplier_user'])))->toBeFalse();
});

test('only platform roles can view audit logs', function () {
    $policy = new AuditLogPolicy();
    $auditLog = AuditLog::factory()->make();

    expect($policy->viewAny(User::factory()->make(['role' => 'super_admin'])))->toBeTrue()
        ->and($policy->viewAny(User::factory()->make(['role' => 'admin'])))->toBeTrue()
        ->and($policy->viewAny(User::factory()->make(['role' => 'auditor'])))->toBeTrue()
        ->and($policy->viewAny(User::factory()->make(['role' => 'supplier_admin'])))->toBeFalse()
        ->and($policy->viewAny(User::factory()->make(['role' => 'supplier_user'])))->toBeFalse()
        ->and($policy->view(User::factory()->make(['role' => 'auditor']), $auditLog))->toBeTrue();
});
