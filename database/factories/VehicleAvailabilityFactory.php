<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\VehicleAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VehicleAvailability>
 */
class VehicleAvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $officeCode = Str::upper(fake()->bothify('OF###'));

        return [
            'uuid' => (string) Str::uuid(),
            'supplier_id' => Supplier::factory(),
            'office_id' => null,
            'location_type' => 'office',
            'location_code' => $officeCode,
            'office_code' => $officeCode,
            'iata_code' => null,
            'vehicle_class' => fake()->randomElement(['Economy', 'Compact', 'SUV', 'Van']),
            'acriss_code' => fake()->randomElement(['ECAR', 'CCAR', 'IFAR', 'FVAR']),
            'available_quantity' => fake()->numberBetween(0, 25),
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addDays(7)->toDateString(),
            'status' => 'available',
            'metadata' => [],
        ];
    }
}
