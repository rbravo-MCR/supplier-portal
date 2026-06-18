<?php

namespace App\Modules\Promotions\Domain\Rules;

use App\Modules\Promotions\Domain\Exceptions\InvalidPromotionData;

class VolumeTierDaysMustBeValid
{
    /**
     * @param  list<array{min_days: int, max_days: int|null, discount_value: float}>  $tiers
     */
    public function validate(array $tiers): bool
    {
        if (empty($tiers)) {
            throw InvalidPromotionData::because('Volume promotions require at least one discount tier.');
        }

        $previousMax = 0;

        foreach ($tiers as $index => $tier) {
            $min = (int) $tier['min_days'];
            $max = $tier['max_days'] !== null ? (int) $tier['max_days'] : null;

            if ($min < 1) {
                throw InvalidPromotionData::because("Tier {$index}: minimum days must be at least 1.");
            }

            if ($max !== null && $max < $min) {
                throw InvalidPromotionData::because("Tier {$index}: maximum days must be greater than or equal to minimum days.");
            }

            if ($min <= $previousMax) {
                throw InvalidPromotionData::because("Tier {$index}: days overlap with a previous tier.");
            }

            $previousMax = $max ?? PHP_INT_MAX;
        }

        return true;
    }
}
