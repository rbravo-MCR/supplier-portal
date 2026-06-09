<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->streetName();

        return [
            'uuid' => (string) Str::uuid(),
            'city_id' => City::factory(),
            'name' => $name,
            'code' => Str::upper(fake()->unique()->bothify('Z###')),
            'status' => 'active',
        ];
    }
}
