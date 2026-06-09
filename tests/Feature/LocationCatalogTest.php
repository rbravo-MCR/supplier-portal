<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\Zone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('location catalog stores countries cities zones and offices', function () {
    $country = Country::factory()->create([
        'name' => 'Mexico',
        'iso2' => 'MX',
        'iso3' => 'MEX',
    ]);

    $city = City::factory()->for($country)->create([
        'name' => 'Cancun',
        'code' => 'CUN',
    ]);

    $zone = Zone::factory()->for($city)->create([
        'name' => 'Hotel Zone',
        'code' => 'HZ',
    ]);

    $office = Office::factory()->for($zone)->create([
        'name' => 'Cancun Airport',
        'code' => 'CUN01',
        'iata_code' => 'CUN',
    ]);

    $this->assertModelExists($country);
    $this->assertModelExists($city);
    $this->assertModelExists($zone);
    $this->assertModelExists($office);

    expect($office->zone->is($zone))->toBeTrue()
        ->and($office->zone->city->is($city))->toBeTrue()
        ->and($office->zone->city->country->is($country))->toBeTrue();
});

test('location catalog search ignores accents', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Accent-insensitive search uses PostgreSQL unaccent.');
    }

    $country = Country::factory()->create([
        'name' => 'México',
        'iso2' => 'MX',
        'iso3' => 'MEX',
    ]);
    $city = City::factory()->for($country)->create([
        'name' => 'Cancún',
        'code' => 'CUN',
    ]);
    Zone::factory()->for($city)->create([
        'name' => 'Zona Hotelera Cancún',
        'code' => 'ZH',
    ]);

    expect(Country::query()->search('Mexico')->pluck('name')->all())->toBe(['México'])
        ->and(City::query()->search('Cancun')->pluck('name')->all())->toBe(['Cancún'])
        ->and(Zone::query()->search('Cancun')->pluck('name')->all())->toBe(['Zona Hotelera Cancún']);
});

test('vehicle availability can resolve a supplier office by iata', function () {
    config(['services.supplier_service.token' => 'test-token']);

    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $office = Office::factory()
        ->for(Zone::factory()->for(City::factory()->for(Country::factory())))
        ->for($supplier)
        ->create([
            'code' => 'CUN01',
            'iata_code' => 'CUN',
        ]);

    $this->postJson('/api/supplier-service/vehicle-availability', [
        'supplier_code' => 'DEMO',
        'items' => [
            [
                'iata_code' => 'cun',
                'vehicle_class' => 'compact',
                'acriss_code' => 'ccar',
                'available_quantity' => 8,
                'valid_from' => '2026-06-13',
                'valid_to' => '2026-06-14',
            ],
        ],
    ], [
        'Authorization' => 'Bearer test-token',
    ])
        ->assertOk()
        ->assertJsonPath('data.0.office.code', 'CUN01')
        ->assertJsonPath('data.0.office.iata_code', 'CUN');

    $this->assertDatabaseHas('vehicle_availabilities', [
        'supplier_id' => $supplier->id,
        'office_id' => $office->id,
        'location_type' => 'iata',
        'location_code' => 'CUN',
    ]);
});

test('fenix location import merges global and mexico zones without duplicates', function () {
    config([
        'database.connections.fenix_mysql' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
    ]);

    Schema::connection('fenix_mysql')->create('api_paises', function (Blueprint $table) {
        $table->string('code')->nullable();
        $table->string('name')->nullable();
    });

    Schema::connection('fenix_mysql')->create('api_destinos', function (Blueprint $table) {
        $table->string('code');
        $table->string('name');
        $table->string('country_code')->nullable();
    });

    foreach (['api_zonas', 'api_zonas_mx'] as $tableName) {
        Schema::connection('fenix_mysql')->create($tableName, function (Blueprint $table) {
            $table->integer('zone_code')->nullable();
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('destination_code');
        });
    }

    DB::connection('fenix_mysql')->table('api_paises')->insert([
        'code' => 'MX',
        'name' => 'Mexico',
    ]);
    DB::connection('fenix_mysql')->table('api_destinos')->insert([
        'code' => 'CUN',
        'name' => 'Cancun',
        'country_code' => 'MX',
    ]);
    DB::connection('fenix_mysql')->table('api_zonas')->insert([
        ['destination_code' => 'CUN', 'zone_code' => 1, 'name' => 'Hotel Zone', 'description' => 'Hotel Zone'],
        ['destination_code' => 'CUN', 'zone_code' => 2, 'name' => 'Downtown', 'description' => 'Downtown'],
    ]);
    DB::connection('fenix_mysql')->table('api_zonas_mx')->insert([
        ['destination_code' => 'CUN', 'zone_code' => 1, 'name' => 'Hotel Zone', 'description' => 'Hotel Zone'],
    ]);

    $this->artisan('catalog:import-fenix-locations', ['--source' => 'all'])
        ->assertSuccessful();

    expect(Country::query()->count())->toBe(1)
        ->and(City::query()->count())->toBe(1)
        ->and(Zone::query()->count())->toBe(2);
});
