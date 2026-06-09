<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
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
            'reservation_code' => Str::upper(fake()->unique()->bothify('RSV-####')),
            'customer_name' => fake()->name(),
            'vehicle_class' => fake()->randomElement(['Economy', 'Compact', 'SUV']),
            'pickup_office_code' => Str::upper(fake()->bothify('OF###')),
            'dropoff_office_code' => Str::upper(fake()->bothify('OF###')),
            'pickup_at' => now()->addDay(),
            'dropoff_at' => now()->addDays(3),
            'total_amount' => fake()->randomFloat(2, 50, 900),
            'currency' => 'USD',
            'status' => 'pending',
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the booking is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }
}
