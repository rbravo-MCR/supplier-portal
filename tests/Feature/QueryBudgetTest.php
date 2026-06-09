<?php

use App\Models\Booking;
use App\Models\City;
use App\Models\Country;
use App\Models\Office;
use App\Models\Rate;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Zone;
use App\Modules\Booking\Application\UseCases\ListPendingBookings;
use App\Modules\Pricing\Application\UseCases\ListRates;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('bookings list stays within query budget', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);

    Booking::factory()->count(20)->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    [$bookings, $queryCount] = countQueries(fn () => app(ListPendingBookings::class)->handle(perPage: 10));

    expect($bookings)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($queryCount)->toBeLessThanOrEqual(10);
});

test('pricing list stays within query budget', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Rate::factory()->count(20)->create([
        'supplier_id' => $supplier->id,
        'status' => 'active',
    ]);

    $this->actingAs($user);

    [$rates, $queryCount] = countQueries(fn () => app(ListRates::class)->handle(perPage: 10));

    expect($rates)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($queryCount)->toBeLessThanOrEqual(10);
});

test('offices page country filter stays within query budget', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $country = Country::factory()->create(['name' => 'Mexico', 'iso2' => 'MX']);
    $city = City::factory()->for($country)->create(['name' => 'Cancun', 'code' => 'CUN']);
    $zone = Zone::factory()->for($city)->create(['name' => 'Hotel Zone', 'code' => '10']);

    Office::factory()->count(20)->for($zone)->for($supplier)->sequence(
        fn (Sequence $sequence): array => [
            'name' => sprintf('Office %02d', $sequence->index + 1),
            'code' => sprintf('CUN%02d', $sequence->index + 1),
        ],
    )->create();

    [$component, $queries] = collectQueries(fn () => Livewire::actingAs($user)
        ->test('pages::offices')
        ->set('filterCountryId', $country->id)
    );

    $component->assertSee('Office 01');

    expect($queries)
        ->not->toContain('select "id" from "cities" where "country_id" = ?')
        ->not->toContain('select "id" from "zones" where "city_id" in (?)')
        ->and(count($queries))->toBeLessThanOrEqual(30);
});

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return array{0: T, 1: int}
 */
function countQueries(callable $callback): array
{
    $queryCount = 0;

    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    return [$callback(), $queryCount];
}

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return array{0: T, 1: list<string>}
 */
function collectQueries(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    return [$callback(), $queries];
}
