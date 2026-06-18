<?php

namespace App\Modules\Promotions\Application\UseCases;

use App\Models\Promotion;
use App\Modules\Promotions\Application\Contracts\PromotionRepository;

class DeletePromotion
{
    public function __construct(
        private readonly PromotionRepository $promotions,
    ) {}

    public function handle(Promotion $promotion): void
    {
        if ($promotion->bookingPromotions()->exists()) {
            throw new \RuntimeException('Cannot delete a promotion that has been applied to bookings.');
        }

        $this->promotions->delete($promotion);
    }
}
