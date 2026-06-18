<?php

namespace App\Modules\Promotions\Application\DTOs;

class CalculatePriceData
{
    public function __construct(
        public readonly int $supplierId,
        public readonly string $officeCode,
        public readonly string $acrissCode,
        public readonly string $pickupAt,
        public readonly string $dropoffAt,
        public readonly float $baseAmount,
        public readonly string $currency,
    ) {}
}
