<?php

namespace App\Modules\Pricing\Application\DTOs;

class PublishRateData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly string $officeCode,
        public readonly string $vehicleClass,
        public readonly string $acrissCode,
        public readonly string $ratePlanCode,
        public readonly string $currency,
        public readonly float|int|string $basePrice,
        public readonly string $validFrom,
        public readonly string $validTo,
        public readonly ?int $minDays,
        public readonly ?int $maxDays,
        public readonly int $createdBy,
    ) {}
}
