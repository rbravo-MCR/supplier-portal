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

test('supplier admin can edit a vehicle category from the categories page', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $catalog = VehicleCategoryCatalog::factory()->create([
        'code' => 'H2',
        'name_es' => 'SUV mediano automatico',
    ]);
    VehicleCategoryAcrissCode::factory()->create([
        'vehicle_category_catalog_id' => $catalog->id,
        'code' => 'IFAR',
    ]);
    $category = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_category_catalog_id' => $catalog->id,
        'supplier_code' => 'OLD',
        'name' => 'SUV mediano automatico',
        'code' => 'H2',
        'acriss_prefix' => 'IFAR',
        'description' => 'Descripción anterior',
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::categories')
        ->call('editCategory', $category->id)
        ->assertSet('editingCategoryId', $category->id)
        ->assertSet('editSupplierCode', 'OLD')
        ->set('editSupplierCode', 'new_code')
        ->set('editAcrissCode', 'IFAR')
        ->set('editDescription', 'Descripción actualizada')
        ->set('editStatus', 'inactive')
        ->call('updateCategory')
        ->assertHasNoErrors()
        ->assertSet('showEditCategoryModal', false);

    $this->assertDatabaseHas('vehicle_categories', [
        'id' => $category->id,
        'supplier_id' => $supplier->id,
        'supplier_code' => 'NEW_CODE',
        'acriss_prefix' => 'IFAR',
        'description' => 'Descripción actualizada',
        'status' => 'inactive',
    ]);
});

test('supplier admin logical delete only marks a vehicle category inactive', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $category = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::categories')
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('vehicle_categories', [
        'id' => $category->id,
        'status' => 'inactive',
    ]);
});

test('category actions are only visible for supplier admin role id four', function () {
    $supplier = Supplier::factory()->create();
    $adminUser = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $pricingUser = User::factory()->create([
        'role' => 'supplier_pricing',
        'supplier_id' => $supplier->id,
    ]);
    $category = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($adminUser)
        ->get(route('portal.categories'))
        ->assertOk()
        ->assertSee('data-test="category-actions-header"', false)
        ->assertSee("data-test=\"category-edit-{$category->id}\"", false)
        ->assertSee("data-test=\"category-delete-{$category->id}\"", false);

    $this->actingAs($pricingUser)
        ->get(route('portal.categories'))
        ->assertOk()
        ->assertDontSee('data-test="category-actions-header"', false)
        ->assertDontSee("data-test=\"category-edit-{$category->id}\"", false)
        ->assertDontSee("data-test=\"category-delete-{$category->id}\"", false);
});

test('non supplier admin users cannot edit or delete categories by calling livewire actions', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_pricing',
        'supplier_id' => $supplier->id,
    ]);
    $category = VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => 'active',
    ]);

    Livewire::actingAs($user)
        ->test('pages::categories')
        ->call('editCategory', $category->id)
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test('pages::categories')
        ->call('deleteCategory', $category->id)
        ->assertForbidden();

    $this->assertDatabaseHas('vehicle_categories', [
        'id' => $category->id,
        'status' => 'active',
    ]);
});
