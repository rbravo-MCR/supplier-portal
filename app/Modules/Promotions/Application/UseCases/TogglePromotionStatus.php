<?php

namespace App\Modules\Promotions\Application\UseCases;

use App\Models\Promotion;

class TogglePromotionStatus
{
    public function handle(Promotion $promotion): Promotion
    {
        $promotion->update([
            'status' => $promotion->status === 'active' ? 'inactive' : 'active',
        ]);

        return $promotion->fresh();
    }
}
