<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::upper(fake()->unique()->bothify('OF###'));

        return [
            'uuid' => (string) Str::uuid(),
            'zone_id' => Zone::factory(),
            'supplier_id' => null,
            'name' => fake()->company().' '.fake()->randomElement(['Airport', 'Downtown', 'Station']),
            'code' => $code,
            'iata_code' => null,
            'type' => 'office',
            'status' => 'active',
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
