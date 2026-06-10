<?php

use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleAvailability;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('availabilities page displays vehicle availability windows', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    VehicleAvailability::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_class' => 'Economy',
        'acriss_code' => 'ECAR',
        'location_code' => 'CUN01',
        'available_quantity' => 5,
        'status' => 'available',
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
    ]);

    Livewire::actingAs($user)
        ->test('pages::availabilities')
        ->set('filterDate', '2026-06-15')
        ->assertSee('Economy')
        ->assertSee('ECAR')
        ->assertSee('CUN01');
});

test('availabilities page filters by date', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    VehicleAvailability::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'location_code' => 'MID01',
        'status' => 'available',
        'valid_from' => '2026-07-01',
        'valid_to' => '2026-07-31',
    ]);

    VehicleAvailability::factory()->create([
        'supplier_id' => $supplier->id,
        'vehicle_class' => 'Van',
        'acriss_code' => 'FVAR',
        'location_code' => 'LAX01',
        'status' => 'available',
        'valid_from' => '2026-08-01',
        'valid_to' => '2026-08-31',
    ]);

    Livewire::actingAs($user)
        ->test('pages::availabilities')
        ->set('filterDate', '2026-07-15')
        ->assertSee('MID01')
        ->assertDontSee('LAX01');
});

test('availabilities page filters by status', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    VehicleAvailability::factory()->create([
        'supplier_id' => $supplier->id,
        'acriss_code' => 'ECAR',
        'status' => 'available',
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
    ]);

    VehicleAvailability::factory()->create([
        'supplier_id' => $supplier->id,
        'acriss_code' => 'IFAR',
        'status' => 'unavailable',
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
    ]);

    Livewire::actingAs($user)
        ->test('pages::availabilities')
        ->set('filterDate', '2026-06-15')
        ->set('filterStatus', 'available')
        ->assertSee('ECAR')
        ->assertDontSee('IFAR');
});

test('availabilities page stays within query budget', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    VehicleAvailability::factory()->count(20)->create([
        'supplier_id' => $supplier->id,
        'status' => 'available',
        'valid_from' => '2026-01-01',
        'valid_to' => '2026-12-31',
    ]);

    $queries = [];
    DB::listen(fn ($q) => $queries[] = $q->sql);

    Livewire::actingAs($user)
        ->test('pages::availabilities')
        ->set('filterDate', '2026-06-15');

    expect(count($queries))->toBeLessThanOrEqual(15);
});
