<?php

namespace Database\Factories;

use App\Models\VehicleCategoryAcrissCode;
use App\Models\VehicleCategoryCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleCategoryAcrissCode>
 */
class VehicleCategoryAcrissCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_category_catalog_id' => VehicleCategoryCatalog::factory(),
            'code' => fake()->unique()->regexify('[A-Z]{4}'),
        ];
    }
}
