<?php

namespace Database\Factories;

use App\Models\VehicleCategoryCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VehicleCategoryCatalog>
 */
class VehicleCategoryCatalogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::upper(fake()->unique()->bothify('?##'));

        return [
            'code' => $code,
            'name_es' => fake()->randomElement(['Economico manual', 'Compacto automatico', 'SUV compacto automatico']),
            'name_en' => fake()->randomElement(['Economy manual', 'Compact automatic', 'Compact SUV automatic']),
            'vehicle_body_type' => fake()->randomElement(['CAR', 'SUV', 'VAN']),
            'passenger_capacity_min' => 5,
            'passenger_capacity_max' => 5,
            'category_family' => Str::substr($code, 0, 1),
            'transmission_type' => fake()->randomElement(['MANUAL', 'AUTOMATIC']),
            'fuel_type' => fake()->randomElement(['GASOLINE', 'HYBRID']),
            'variant_key' => "{$code}|AUTOMATIC|GASOLINE|5|CAR",
            'status' => 'active',
        ];
    }
}
