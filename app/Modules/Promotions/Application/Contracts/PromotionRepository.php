<?php

namespace App\Modules\Promotions\Application\Contracts;

use App\Models\Promotion;
use App\Modules\Promotions\Application\DTOs\CreatePromotionData;
use App\Modules\Promotions\Application\DTOs\ListPromotionsFilterData;
use App\Modules\Promotions\Application\DTOs\UpdatePromotionData;
use Illuminate\Contracts\Pagination\Paginator;

interface PromotionRepository
{
    public function listForSupplier(?int $supplierId, ListPromotionsFilterData $filter): Paginator;

    public function hasActiveOverlap(int $supplierId, CreatePromotionData $data, ?string $excludeUuid = null): bool;

    public function create(int $supplierId, CreatePromotionData $data): Promotion;

    public function update(Promotion $promotion, UpdatePromotionData $data): Promotion;

    public function delete(Promotion $promotion): void;
}
