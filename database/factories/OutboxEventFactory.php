<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
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
            'aggregate_type' => Booking::class,
            'aggregate_id' => 1,
            'event_type' => 'BookingConfirmed',
            'payload' => [],
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
            'processed_at' => null,
        ];
    }
}
