<?php

namespace App\Modules\Promotions\Application\DTOs;

class UpdatePromotionData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $discountType = null,
        public readonly ?float $discountValue = null,
        public readonly ?string $validFrom = null,
        public readonly ?string $validTo = null,
        public readonly ?bool $appliesToAllOffices = null,
        public readonly ?bool $appliesToAllCategories = null,
        public readonly ?array $officeIds = null,
        public readonly ?array $categoryIds = null,
        public readonly ?array $tiers = null,
    ) {}
}
