<?php

namespace App\Modules\Promotions\Application\UseCases;

use App\Models\Promotion;
use App\Modules\Promotions\Application\Contracts\PromotionRepository;
use App\Modules\Promotions\Application\DTOs\CreatePromotionData;
use App\Modules\Promotions\Application\DTOs\UpdatePromotionData;
use App\Modules\Promotions\Domain\Exceptions\PromotionOverlapDetected;
use App\Modules\Promotions\Domain\Rules\FreeDaysMustBeValid;
use App\Modules\Promotions\Domain\Rules\PromotionDatesMustBeValid;
use App\Modules\Promotions\Domain\Rules\VehicleVolumeTiersMustBeValid;
use App\Modules\Promotions\Domain\Rules\VolumeTierDaysMustBeValid;
use Illuminate\Support\Facades\DB;

class UpdatePromotion
{
    public function __construct(
        private readonly PromotionRepository $promotions,
        private readonly PromotionDatesMustBeValid $datesRule,
        private readonly VolumeTierDaysMustBeValid $tiersRule,
        private readonly FreeDaysMustBeValid $freeDaysRule,
        private readonly VehicleVolumeTiersMustBeValid $vehicleTiersRule,
    ) {}

    public function handle(Promotion $promotion, UpdatePromotionData $data): Promotion
    {
        $validFrom = $data->validFrom ?? $promotion->valid_from->toDateString();
        $validTo = $data->validTo ?? $promotion->valid_to->toDateString();

        $this->datesRule->validate($validFrom, $validTo);

        if ($promotion->type === 'volume' && $data->tiers !== null && $data->tiers !== []) {
            $this->tiersRule->validate($data->tiers);
        }

        if ($promotion->type === 'vehicle_volume' && $data->vehicleTiers !== null && $data->vehicleTiers !== []) {
            $this->vehicleTiersRule->validate($data->vehicleTiers);
        }

        if ($data->discountType === 'free_days' || ($promotion->discount_type === 'free_days' && ($data->minRentalDays !== null || $data->freeDays !== null))) {
            $this->freeDaysRule->validate(
                $data->minRentalDays ?? $promotion->min_rental_days,
                $data->freeDays ?? $promotion->free_days,
            );
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
                        minRentalDays: $promotion->min_rental_days,
                        freeDays: $promotion->free_days,
                        minVehicleCount: $promotion->min_vehicle_count,
                        validFrom: $validFrom,
                        validTo: $validTo,
                        status: $promotion->status,
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
                throw match ($promotion->type) {
                    'seasonal' => PromotionOverlapDetected::forSeasonal(),
                    'vehicle_volume' => PromotionOverlapDetected::forVehicleVolume(),
                    default => PromotionOverlapDetected::forVolume(),
                };
            }

            return $this->promotions->update($promotion, $data);
        });
    }
}
