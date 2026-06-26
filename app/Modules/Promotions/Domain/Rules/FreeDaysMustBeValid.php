<?php

namespace App\Modules\Promotions\Domain\Rules;

use App\Modules\Promotions\Domain\Exceptions\InvalidPromotionData;

class FreeDaysMustBeValid
{
    public function validate(?int $minRentalDays, ?int $freeDays): bool
    {
        if ($freeDays === null || $freeDays < 1) {
            throw InvalidPromotionData::because('Free days must be at least 1.');
        }

        if ($minRentalDays === null || $minRentalDays < 1) {
            throw InvalidPromotionData::because('Minimum rental days must be at least 1.');
        }

        if ($freeDays >= $minRentalDays) {
            throw InvalidPromotionData::because('Free days must be less than minimum rental days.');
        }

        return true;
    }
}
