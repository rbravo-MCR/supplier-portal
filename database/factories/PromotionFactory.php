<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'supplier_id' => Supplier::factory(),
            'name' => fake()->words(3, true),
            'type' => 'seasonal',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'min_rental_days' => null,
            'free_days' => null,
            'min_vehicle_count' => 1,
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'applies_to_all_offices' => true,
            'applies_to_all_categories' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the promotion is seasonal.
     */
    public function seasonal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'seasonal',
        ]);
    }

    /**
     * Indicate that the promotion is volume-based.
     */
    public function volume(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'volume',
        ]);
    }

    /**
     * Indicate that the promotion is vehicle volume-based.
     */
    public function vehicleVolume(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vehicle_volume',
        ]);
    }

    /**
     * Indicate that the promotion is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
