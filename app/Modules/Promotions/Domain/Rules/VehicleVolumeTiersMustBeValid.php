<?php

namespace App\Modules\Promotions\Domain\Rules;

use App\Modules\Promotions\Domain\Exceptions\InvalidPromotionData;

class VehicleVolumeTiersMustBeValid
{
    /**
     * @param  list<array{min_vehicles: int, max_vehicles: int|null, discount_value: float}>  $tiers
     */
    public function validate(array $tiers): bool
    {
        if (empty($tiers)) {
            throw InvalidPromotionData::because('Vehicle volume promotions require at least one discount tier.');
        }

        $previousMax = 0;

        foreach ($tiers as $index => $tier) {
            $min = (int) $tier['min_vehicles'];
            $max = $tier['max_vehicles'] !== null ? (int) $tier['max_vehicles'] : null;

            if ($min < 1) {
                throw InvalidPromotionData::because("Tier {$index}: minimum vehicles must be at least 1.");
            }

            if ($max !== null && $max < $min) {
                throw InvalidPromotionData::because("Tier {$index}: maximum vehicles must be greater than or equal to minimum vehicles.");
            }

            if ($min <= $previousMax) {
                throw InvalidPromotionData::because("Tier {$index}: vehicle ranges overlap with a previous tier.");
            }

            $previousMax = $max ?? PHP_INT_MAX;
        }

        return true;
    }
}
