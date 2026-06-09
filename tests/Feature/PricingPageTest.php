<?php

use App\Models\Rate;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('pricing page displays vehicle rate data', function () {
    $supplier = Supplier::factory()->create(['name' => 'Demo Rent', 'code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Rate::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'office_code' => 'CUN',
        'rate_plan_code' => 'STD',
        'base_price' => 125.50,
        'currency' => 'USD',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.prices'))
        ->assertOk()
        ->assertSee('Vehículos y tarifas')
        ->assertSee('SUV')
        ->assertSee('IFAR')
        ->assertSee('CUN')
        ->assertSee('USD 125.50');
});

test('pricing form publishes a new vehicle rate', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::pricing')
        ->set('officeCode', 'cun')
        ->set('vehicleClass', 'suv')
        ->set('acrissCode', 'ifar')
        ->set('ratePlanCode', 'std')
        ->set('currency', 'usd')
        ->set('basePrice', '199.99')
        ->set('validFrom', now()->toDateString())
        ->set('validTo', now()->addDays(10)->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('SUV')
        ->assertSee('IFAR')
        ->assertSee('USD 199.99');

    $this->assertDatabaseHas('rates', [
        'supplier_id' => $supplier->id,
        'office_code' => 'CUN',
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
        'currency' => 'USD',
        'base_price' => 199.99,
        'status' => 'active',
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
