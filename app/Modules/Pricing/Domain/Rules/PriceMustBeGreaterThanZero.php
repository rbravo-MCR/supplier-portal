<?php

namespace App\Modules\Pricing\Domain\Rules;

use App\Modules\Pricing\Domain\Exceptions\InvalidRateData;

class PriceMustBeGreaterThanZero
{
    /**
     * Validate the base price.
     */
    public function validate(float|int|string $basePrice): bool
    {
        if ((float) $basePrice <= 0) {
            throw InvalidRateData::because('Base price must be greater than zero.');
        }

        return true;
    }
}
