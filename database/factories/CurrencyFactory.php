<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::upper(fake()->unique()->lexify('X??'));

        return [
            'code' => $code,
            'numeric_code' => fake()->unique()->numerify('###'),
            'name' => $code,
            'symbol' => $code,
            'decimal_places' => 2,
            'is_active' => true,
        ];
    }
}
