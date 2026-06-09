<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('offices page displays offices with location catalog', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $country = Country::factory()->create(['name' => 'Mexico', 'iso2' => 'MX']);
    $city = City::factory()->for($country)->create(['name' => 'Cancun', 'code' => 'CUN']);
    $zone = Zone::factory()->for($city)->create(['name' => 'Hotel Zone', 'code' => '10']);

    Office::factory()->for($zone)->for($supplier)->create([
        'name' => 'Cancun Airport',
        'code' => 'CUN01',
        'iata_code' => 'CUN',
    ]);

    $this->actingAs($user)
        ->get(route('portal.offices'))
        ->assertOk()
        ->assertSee('Directorio de oficinas')
        ->assertSee('Cancun Airport')
        ->assertSee('CUN01')
        ->assertSee('CUN')
        ->assertSee('Hotel Zone');
});

test('offices form creates supplier office', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $zone = Zone::factory()
        ->for(City::factory()->for(Country::factory()))
        ->create();

    Livewire::actingAs($user)
        ->test('pages::offices')
        ->set('zoneId', $zone->id)
        ->set('name', 'Merida Downtown')
        ->set('code', 'mid01')
        ->set('iataCode', 'mid')
        ->set('type', 'office')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Merida Downtown')
        ->assertSee('MID01');

    $this->assertDatabaseHas('offices', [
        'supplier_id' => $supplier->id,
        'zone_id' => $zone->id,
        'name' => 'Merida Downtown',
        'code' => 'MID01',
        'iata_code' => 'MID',
        'type' => 'office',
        'status' => 'active',
    ]);
});

test('offices page renders supplier selector for platform users', function () {
    $activeSupplier = Supplier::factory()->create([
        'name' => 'Active Supplier',
        'code' => 'ACTIVE',
        'status' => 'active',
    ]);
    Supplier::factory()->create([
        'name' => 'Inactive Supplier',
        'code' => 'INACTIVE',
        'status' => 'inactive',
    ]);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::offices')
        ->assertSee($activeSupplier->name)
        ->assertDontSee('Inactive Supplier');
});

test('supplier active scope filters inactive suppliers', function () {
    $activeSupplier = Supplier::factory()->create(['status' => 'active']);
    Supplier::factory()->create(['status' => 'inactive']);

    expect(Supplier::query()->active()->pluck('id')->all())->toBe([$activeSupplier->id]);
});

test('offices page rebuilds stale object catalog cache as array rows', function () {
    Cache::put('suppliers.active', unserialize('O:19:"Missing\\CachedClass":0:{}'));

    $supplier = Supplier::factory()->create([
        'name' => 'Recovered Supplier',
        'code' => 'RECOVERED',
        'status' => 'active',
    ]);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::offices')
        ->assertSee($supplier->name);

    expect(Cache::get('suppliers.active'))
        ->toBeArray()
        ->sequence(fn ($row) => $row->toHaveKey('id'));
});

test('offices form selects mexico by default', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);
    $mexico = Country::factory()->create(['name' => 'México', 'iso2' => 'MX']);

    Livewire::actingAs($user)
        ->test('pages::offices')
        ->assertSet('countryId', $mexico->id)
        ->assertSee('México · MX');
});

test('offices directory filters offices by country without changing visible results', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $mexico = Country::factory()->create(['name' => 'Mexico', 'iso2' => 'MX']);
    $usa = Country::factory()->create(['name' => 'United States', 'iso2' => 'US']);
    $mexicoZone = Zone::factory()
        ->for(City::factory()->for($mexico)->create(['name' => 'Cancun', 'code' => 'CUN']))
        ->create(['name' => 'Hotel Zone']);
    $usaZone = Zone::factory()
        ->for(City::factory()->for($usa)->create(['name' => 'Miami', 'code' => 'MIA']))
        ->create(['name' => 'Airport Zone']);

    Office::factory()->for($mexicoZone)->for($supplier)->create(['name' => 'Cancun Airport', 'code' => 'CUN01']);
    Office::factory()->for($usaZone)->for($supplier)->create(['name' => 'Miami Airport', 'code' => 'MIA01']);

    Livewire::actingAs($user)
        ->test('pages::offices')
        ->set('filterCountryId', $mexico->id)
        ->assertSee('Cancun Airport')
        ->assertDontSee('Miami Airport');
});

test('searchable catalog selects debounce live searches for half a second', function () {
    $component = file_get_contents(resource_path('views/components/portal-searchable-select.blade.php'));

    expect($component)
        ->toContain('wire:model.live.debounce.500ms')
        ->not->toContain('wire:model.live="{{ $searchProperty }}"');
});
