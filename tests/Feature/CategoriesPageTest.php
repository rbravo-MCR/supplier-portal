<?php

use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleCategory;
use Livewire\Livewire;

test('categories page displays vehicle categories', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    VehicleCategory::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'SUV',
        'code' => 'SUV',
        'acriss_prefix' => 'IF',
        'description' => 'Vehículos familiares',
    ]);

    $this->actingAs($user)
        ->get(route('portal.categories'))
        ->assertOk()
        ->assertSee('Catálogo de categorías')
        ->assertSee('SUV')
        ->assertSee('IF')
        ->assertSee('Vehículos familiares');
});

test('categories form creates a vehicle category', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::categories')
        ->set('name', 'Compacto')
        ->set('code', 'compact')
        ->set('acrissPrefix', 'CD')
        ->set('description', 'Auto compacto de uso urbano')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Compacto')
        ->assertSee('COMPACT');

    $this->assertDatabaseHas('vehicle_categories', [
        'supplier_id' => $supplier->id,
        'name' => 'Compacto',
        'code' => 'COMPACT',
        'acriss_prefix' => 'CD',
        'description' => 'Auto compacto de uso urbano',
        'status' => 'active',
    ]);
});
