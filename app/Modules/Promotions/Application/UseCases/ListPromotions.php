<?php

namespace App\Modules\Promotions\Application\UseCases;

use App\Modules\Promotions\Application\Contracts\PromotionRepository;
use App\Modules\Promotions\Application\DTOs\ListPromotionsFilter;
use Illuminate\Contracts\Pagination\Paginator;

class ListPromotions
{
    public function __construct(
        private readonly PromotionRepository $promotions,
    ) {}

    public function handle(int $supplierId, ListPromotionsFilter $filter): Paginator
    {
        return $this->promotions->listForSupplier($supplierId, $filter);
    }
}
