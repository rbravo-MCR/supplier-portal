<?php

use App\Models\Country;
use App\Models\Currency;
use App\Models\Rate;
use Database\Seeders\CurrencySeeder;
use Illuminate\Support\Facades\Schema;

test('currency seed creates the initial iso catalog idempotently', function () {
    $this->seed(CurrencySeeder::class);
    $this->seed(CurrencySeeder::class);

    expect(Currency::query()->where('code', 'USD')->first())
        ->numeric_code->toBe('840')
        ->name->toBe('US Dollar')
        ->decimal_places->toBe(2)
        ->is_active->toBeTrue()
        ->and(Currency::query()->where('code', 'CLP')->first())
        ->decimal_places->toBe(0)
        ->and(Currency::query()->where('code', 'JPY')->first())
        ->decimal_places->toBe(0)
        ->and(Currency::query()->where('code', 'KRW')->first())
        ->decimal_places->toBe(0)
        ->and(Currency::query()->where('code', 'USD')->count())->toBe(1);
});

test('countries can be related to a currency', function () {
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'MXN'],
        ['numeric_code' => '484', 'name' => 'Mexican Peso', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );
    $country = Country::factory()->for($currency)->create([
        'iso2' => 'MX',
        'iso3' => 'MEX',
    ]);

    expect($country->currency->is($currency))->toBeTrue()
        ->and($currency->countries()->whereKey($country)->exists())->toBeTrue();
});

test('rates use currency ids instead of free text currency values', function () {
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );

    $rate = Rate::factory()->for($currency)->create();

    expect(Schema::hasColumn('rates', 'currency_id'))->toBeTrue()
        ->and(Schema::hasColumn('rates', 'currency'))->toBeFalse()
        ->and($rate->currency->is($currency))->toBeTrue();
});
