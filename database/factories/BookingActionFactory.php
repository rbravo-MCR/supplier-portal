<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingAction;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingAction>
 */
class BookingActionFactory extends Factory
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
            'booking_id' => Booking::factory(),
            'supplier_id' => Supplier::factory(),
            'user_id' => User::factory(),
            'action' => 'confirmed',
            'reason' => null,
            'metadata' => [],
        ];
    }
}
