<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'uuid' => (string) Str::uuid(),
            'country_id' => Country::factory(),
            'name' => $name,
            'code' => Str::upper(Str::substr(Str::slug($name, ''), 0, 6)),
            'status' => 'active',
        ];
    }
}
