<?php

namespace App\Modules\Promotions\Application\UseCases;

use App\Models\Promotion;
use App\Modules\Promotions\Application\Contracts\PromotionRepository;
use App\Modules\Promotions\Application\DTOs\CreatePromotionData;
use App\Modules\Promotions\Application\DTOs\UpdatePromotionData;
use App\Modules\Promotions\Domain\Exceptions\PromotionOverlapDetected;
use App\Modules\Promotions\Domain\Rules\PromotionDatesMustBeValid;
use App\Modules\Promotions\Domain\Rules\VolumeTierDaysMustBeValid;
use Illuminate\Support\Facades\DB;

class UpdatePromotion
{
    public function __construct(
        private readonly PromotionRepository $promotions,
        private readonly PromotionDatesMustBeValid $datesRule,
        private readonly VolumeTierDaysMustBeValid $tiersRule,
    ) {}

    public function handle(Promotion $promotion, UpdatePromotionData $data): Promotion
    {
        $validFrom = $data->validFrom ?? $promotion->valid_from->toDateString();
        $validTo = $data->validTo ?? $promotion->valid_to->toDateString();

        $this->datesRule->validate($validFrom, $validTo);

        if ($promotion->type === 'volume' && $data->tiers !== null) {
            $this->tiersRule->validate($data->tiers);
        }

        return DB::transaction(function () use ($promotion, $data, $validFrom, $validTo): Promotion {
            if (
                ($data->validFrom !== null || $data->validTo !== null)
                && $this->promotions->hasActiveOverlap(
                    $promotion->supplier_id,
                    new CreatePromotionData(
                        name: $promotion->name,
                        type: $promotion->type,
                        discountType: $promotion->discount_type,
                        discountValue: (float) $promotion->discount_value,
                        validFrom: $validFrom,
                        validTo: $validTo,
                        appliesToAllOffices: $promotion->applies_to_all_offices,
                        appliesToAllCategories: $promotion->applies_to_all_categories,
                        officeIds: [],
                        categoryIds: [],
                        tiers: [],
                        createdBy: $promotion->created_by,
                    ),
                    excludeUuid: $promotion->uuid,
                )
            ) {
                throw $promotion->type === 'seasonal'
                    ? PromotionOverlapDetected::forSeasonal()
                    : PromotionOverlapDetected::forVolume();
            }

            return $this->promotions->update($promotion, $data);
        });
    }
}
