<?php

use App\Modules\Pricing\Domain\Exceptions\InvalidRateData;
use App\Modules\Pricing\Domain\Rules\PriceMustBeGreaterThanZero;
use App\Modules\Pricing\Domain\Rules\RateValidityDatesMustBeValid;

test('price must be greater than zero', function () {
    $rule = new PriceMustBeGreaterThanZero;

    $rule->validate(0);
})->throws(InvalidRateData::class);

test('positive price is accepted', function () {
    $rule = new PriceMustBeGreaterThanZero;

    expect($rule->validate(10.50))->toBeTrue();
});

test('validity dates are required', function () {
    $rule = new RateValidityDatesMustBeValid;

    $rule->validate(null, null);
})->throws(InvalidRateData::class);

test('valid to must be after or equal valid from', function () {
    $rule = new RateValidityDatesMustBeValid;

    $rule->validate('2026-06-10', '2026-06-09');
})->throws(InvalidRateData::class);

test('same day validity is accepted', function () {
    $rule = new RateValidityDatesMustBeValid;

    expect($rule->validate('2026-06-10', '2026-06-10'))->toBeTrue();
});
