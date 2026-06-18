<?php

namespace App\Modules\Promotions\Application\DTOs;

class CreatePromotionData
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $discountType,
        public readonly float $discountValue,
        public readonly string $validFrom,
        public readonly string $validTo,
        public readonly bool $appliesToAllOffices,
        public readonly bool $appliesToAllCategories,
        public readonly array $officeIds,
        public readonly array $categoryIds,
        public readonly array $tiers,
        public readonly int $createdBy,
    ) {}
}
