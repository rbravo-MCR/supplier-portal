<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\PromotionDiscountTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionDiscountTier>
 */
class PromotionDiscountTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'min_days' => 7,
            'max_days' => 13,
            'discount_value' => 10.00,
        ];
    }
}
