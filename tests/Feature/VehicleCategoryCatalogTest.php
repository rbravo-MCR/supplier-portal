<?php

use App\Models\Supplier;
use App\Models\VehicleCategory;
use App\Models\VehicleCategoryAcrissCode;
use App\Models\VehicleCategoryCatalog;
use Illuminate\Support\Facades\Schema;

test('vehicle category catalog tables support the gps category structure', function () {
    expect(Schema::hasColumns('vehicle_category_catalogs', [
        'code',
        'name_es',
        'name_en',
        'vehicle_body_type',
        'passenger_capacity_min',
        'passenger_capacity_max',
        'category_family',
        'transmission_type',
        'fuel_type',
        'variant_key',
        'status',
    ]))->toBeTrue();

    expect(Schema::hasColumns('vehicle_category_acriss_codes', [
        'vehicle_category_catalog_id',
        'code',
    ]))->toBeTrue();

    expect(Schema::hasColumns('vehicle_categories', [
        'vehicle_category_catalog_id',
        'supplier_code',
    ]))->toBeTrue();
});

test('a supplier category can reference a master catalog category with multiple acriss codes', function () {
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'F',
        'name_es' => 'Fullsize sedan automatico',
        'name_en' => 'Full-size sedan automatic',
        'vehicle_body_type' => 'CAR',
        'passenger_capacity_min' => 5,
        'passenger_capacity_max' => 5,
        'category_family' => 'F',
        'transmission_type' => 'AUTOMATIC',
        'fuel_type' => 'GASOLINE',
        'variant_key' => 'F|AUTOMATIC|GASOLINE|5|CAR',
    ]);

    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'FCAR',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'SCAR',
    ]);

    $supplier = Supplier::factory()->create();
    $category = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'supplier_code' => 'FULLSIZE',
        'code' => 'F',
        'name' => 'Fullsize sedan automatico',
        'acriss_prefix' => 'FCAR',
    ]);

    expect($category->catalog->is($catalog))->toBeTrue();
    expect($catalog->acrissCodes()->pluck('code')->all())->toBe(['FCAR', 'SCAR']);
    expect($supplier->vehicleCategories()->first()->is($category))->toBeTrue();
});

test('gps categories csv can be imported into the master catalog', function () {
    $this->artisan('catalog:import-gps-vehicle-categories')
        ->assertSuccessful();

    expect(VehicleCategoryCatalog::query()->count())->toBe(36);

    $catalog = VehicleCategoryCatalog::query()
        ->where('code', 'F')
        ->firstOrFail();

    expect($catalog->name_es)->toBe('Fullsize sedan automatico');
    expect($catalog->vehicle_body_type)->toBe('CAR');
    expect($catalog->passenger_capacity_min)->toBe(5);
    expect($catalog->passenger_capacity_max)->toBe(5);
    expect($catalog->acrissCodes()->orderBy('code')->pluck('code')->all())->toBe(['FCAR', 'SCAR']);

    $this->artisan('catalog:import-gps-vehicle-categories')
        ->assertSuccessful();

    expect(VehicleCategoryCatalog::query()->count())->toBe(36);
    expect(VehicleCategoryAcrissCode::query()->count())->toBe(39);
});
