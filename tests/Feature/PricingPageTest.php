<?php

use App\Models\Currency;
use App\Models\Office;
use App\Models\Rate;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\VehicleCategoryAcrissCode;
use App\Models\VehicleCategoryCatalog;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('pricing page displays vehicle rate data', function () {
    $supplier = Supplier::factory()->create(['name' => 'Demo Rent', 'code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );

    Rate::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'office_code' => 'CUN',
        'rate_plan_code' => 'STD',
        'base_price' => 125.50,
        'currency_id' => $currency->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.prices'))
        ->assertOk()
        ->assertSee('Vehículos y tarifas')
        ->assertSee('SUV')
        ->assertSee('IFAR')
        ->assertSee('CUN')
        ->assertSee('$')
        ->assertSee('125.50');
});

test('pricing form publishes a new vehicle rate', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );
    Office::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Cancun Airport',
        'code' => 'CUN',
        'status' => 'active',
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'SUV',
        'name_es' => 'SUV compacto automatico',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'IFAR',
    ]);
    $vehicleCategory = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'name' => $catalog->name_es,
        'code' => $catalog->code,
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pricing')
        ->set('officeCode', 'CUN')
        ->set('vehicleCategoryId', $vehicleCategory->id)
        ->set('acrissCode', 'IFAR')
        ->set('ratePlanCode', 'std')
        ->set('currencyId', $currency->id)
        ->set('basePrice', '199.99')
        ->set('validFrom', now()->toDateString())
        ->set('validTo', now()->addDays(10)->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('SUV')
        ->assertSee('IFAR')
        ->assertSee('199.99');

    $this->assertDatabaseHas('rates', [
        'supplier_id' => $supplier->id,
        'office_code' => 'CUN',
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
        'currency_id' => $currency->id,
        'base_price' => 199.99,
        'status' => 'active',
    ]);
});

test('supplier user cannot publish rates for another supplier by changing livewire state', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_pricing',
        'supplier_id' => $supplier->id,
    ]);
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );
    Office::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Cancun Airport',
        'code' => 'CUN',
        'status' => 'active',
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'SUV',
        'name_es' => 'SUV compacto automatico',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'IFAR',
    ]);
    $vehicleCategory = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'name' => $catalog->name_es,
        'code' => $catalog->code,
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pricing')
        ->set('supplierId', $otherSupplier->id)
        ->set('officeCode', 'CUN')
        ->set('vehicleCategoryId', $vehicleCategory->id)
        ->set('acrissCode', 'IFAR')
        ->set('ratePlanCode', 'std')
        ->set('currencyId', $currency->id)
        ->set('basePrice', '199.99')
        ->set('validFrom', now()->toDateString())
        ->set('validTo', now()->addDays(10)->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rates', [
        'supplier_id' => $supplier->id,
        'office_code' => 'CUN',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
    ]);

    $this->assertDatabaseMissing('rates', [
        'supplier_id' => $otherSupplier->id,
        'office_code' => 'CUN',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
    ]);
});

test('pricing form rejects vehicle categories from another supplier', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_pricing',
        'supplier_id' => $supplier->id,
    ]);
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );
    Office::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Cancun Airport',
        'code' => 'CUN',
        'status' => 'active',
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'SUV',
        'name_es' => 'SUV compacto automatico',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'IFAR',
    ]);
    $otherVehicleCategory = VehicleCategory::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'name' => $catalog->name_es,
        'code' => $catalog->code,
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pricing')
        ->set('officeCode', 'CUN')
        ->set('vehicleCategoryId', $otherVehicleCategory->id)
        ->set('acrissCode', 'IFAR')
        ->set('ratePlanCode', 'std')
        ->set('currencyId', $currency->id)
        ->set('basePrice', '199.99')
        ->set('validFrom', now()->toDateString())
        ->set('validTo', now()->addDays(10)->toDateString())
        ->call('save')
        ->assertHasErrors(['vehicleCategoryId']);

    $this->assertDatabaseMissing('rates', [
        'supplier_id' => $supplier->id,
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
    ]);
});

test('pricing form rejects offices from another supplier', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_pricing',
        'supplier_id' => $supplier->id,
    ]);
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );
    Office::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Cancun Airport',
        'code' => 'CUN',
        'status' => 'active',
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'SUV',
        'name_es' => 'SUV compacto automatico',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'IFAR',
    ]);
    $vehicleCategory = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'name' => $catalog->name_es,
        'code' => $catalog->code,
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::pricing')
        ->set('officeCode', 'CUN')
        ->set('vehicleCategoryId', $vehicleCategory->id)
        ->set('acrissCode', 'IFAR')
        ->set('ratePlanCode', 'std')
        ->set('currencyId', $currency->id)
        ->set('basePrice', '199.99')
        ->set('validFrom', now()->toDateString())
        ->set('validTo', now()->addDays(10)->toDateString())
        ->call('save')
        ->assertHasErrors(['officeCode']);

    $this->assertDatabaseMissing('rates', [
        'supplier_id' => $supplier->id,
        'office_code' => 'CUN',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
    ]);
});

test('pricing page reads active suppliers from cached array rows', function () {
    $supplier = Supplier::factory()->create(['name' => 'Cached Supplier', 'code' => 'CACHED']);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => null,
    ]);

    Cache::put('suppliers.active', [
        ['id' => $supplier->id, 'name' => $supplier->name, 'code' => $supplier->code],
    ]);

    Livewire::actingAs($user)
        ->test('pages::pricing')
        ->assertSee('Cached Supplier');
});
