<?php

use App\Modules\Supplier\Domain\Rules\SupplierUserLimitRule;

test('supplier user limit allows creation below the limit', function () {
    $rule = new SupplierUserLimitRule();

    expect($rule->allows(currentUsers: 2, maxUsers: 3))->toBeTrue();
});

test('supplier user limit rejects creation at the limit', function () {
    $rule = new SupplierUserLimitRule();

    expect($rule->allows(currentUsers: 3, maxUsers: 3))->toBeFalse();
});

test('supplier user limit supports bulk additions', function () {
    $rule = new SupplierUserLimitRule();

    expect($rule->allows(currentUsers: 2, maxUsers: 4, additionalUsers: 2))->toBeTrue()
        ->and($rule->allows(currentUsers: 2, maxUsers: 4, additionalUsers: 3))->toBeFalse();
});
