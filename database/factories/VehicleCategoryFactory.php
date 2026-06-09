<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\VehicleCategory;
use App\Models\VehicleCategoryCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleCategory>
 */
class VehicleCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'vehicle_category_catalog_id' => VehicleCategoryCatalog::factory(),
            'supplier_code' => fake()->unique()->bothify('SUP-CAT-###'),
            'name' => fake()->randomElement(['SUV', 'Compacto', 'Van']),
            'code' => fake()->unique()->bothify('CAT-###'),
            'acriss_prefix' => fake()->randomElement(['IF', 'CD', 'FV']),
            'description' => fake()->sentence(),
            'status' => 'active',
        ];
    }
}
