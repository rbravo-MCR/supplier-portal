<?php

use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\VehicleCategoryAcrissCode;
use App\Models\VehicleCategoryCatalog;
use Livewire\Livewire;

test('categories page displays vehicle categories', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'H1',
        'name_es' => 'SUV compacto automatico',
    ]);

    VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'supplier_code' => 'SUV_SUP',
        'name' => 'SUV',
        'code' => 'SUV',
        'acriss_prefix' => 'IF',
        'description' => 'Vehículos familiares',
    ]);

    $this->actingAs($user)
        ->get(route('portal.categories'))
        ->assertOk()
        ->assertSee('Catálogo de categorías')
        ->assertSee('SUV compacto automatico')
        ->assertSee('SUV_SUP')
        ->assertSee('IF')
        ->assertSee('Vehículos familiares');
});

test('categories form creates a vehicle category', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'B1',
        'name_es' => 'Compacto manual',
        'name_en' => 'Compact manual',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'CCMR',
    ]);

    Livewire::actingAs($user)
        ->test('pages::categories')
        ->set('vehicleCategoryCatalogId', $catalog->id)
        ->set('acrissCode', 'CCMR')
        ->set('supplierCode', 'compact')
        ->set('description', 'Auto compacto de uso urbano')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Compacto manual')
        ->assertSee('COMPACT');

    $this->assertDatabaseHas('vehicle_categories', [
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'supplier_code' => 'COMPACT',
        'name' => 'Compacto manual',
        'code' => 'B1',
        'acriss_prefix' => 'CCMR',
        'description' => 'Auto compacto de uso urbano',
        'status' => 'active',
    ]);
});

test('supplier user cannot create categories for another supplier by changing livewire state', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'B2',
        'name_es' => 'Economico automatico',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'ECAR',
    ]);

    Livewire::actingAs($user)
        ->test('pages::categories')
        ->set('supplierId', $otherSupplier->id)
        ->set('vehicleCategoryCatalogId', $catalog->id)
        ->set('acrissCode', 'ECAR')
        ->set('supplierCode', 'economy')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('vehicle_categories', [
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'supplier_code' => 'ECONOMY',
        'acriss_prefix' => 'ECAR',
    ]);

    $this->assertDatabaseMissing('vehicle_categories', [
        'supplier_id' => $otherSupplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'supplier_code' => 'ECONOMY',
    ]);
});
