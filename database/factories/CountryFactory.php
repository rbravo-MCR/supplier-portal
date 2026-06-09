<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $iso2 = Str::upper(fake()->unique()->lexify('??'));

        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->unique()->country(),
            'iso2' => $iso2,
            'iso3' => Str::upper(fake()->unique()->lexify('???')),
            'status' => 'active',
        ];
    }
}
