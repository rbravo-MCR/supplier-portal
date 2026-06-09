<?php

namespace App\Modules\Pricing\Domain\Rules;

use App\Modules\Pricing\Domain\Exceptions\InvalidRateData;
use Carbon\CarbonImmutable;
use Throwable;

class RateValidityDatesMustBeValid
{
    /**
     * Validate rate validity dates.
     */
    public function validate(?string $validFrom, ?string $validTo): bool
    {
        if ($validFrom === null || $validTo === null) {
            throw InvalidRateData::because('Validity dates are required.');
        }

        try {
            $from = CarbonImmutable::parse($validFrom)->startOfDay();
            $to = CarbonImmutable::parse($validTo)->startOfDay();
        } catch (Throwable) {
            throw InvalidRateData::because('Validity dates must be valid dates.');
        }

        if ($to->lt($from)) {
            throw InvalidRateData::because('Valid to must be after or equal valid from.');
        }

        return true;
    }
}
