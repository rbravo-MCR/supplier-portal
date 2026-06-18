<?php

namespace App\Modules\Promotions\Domain\Rules;

use App\Modules\Promotions\Domain\Exceptions\InvalidPromotionData;

class PromotionDatesMustBeValid
{
    public function validate(string $validFrom, string $validTo): bool
    {
        if ($validFrom > $validTo) {
            throw InvalidPromotionData::because('Valid from date must be before or equal to valid to date.');
        }

        return true;
    }
}
