<?php

namespace App\Modules\Promotions\Application\UseCases;

use App\Models\Promotion;
use App\Modules\Promotions\Application\DTOs\CalculatePriceData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalculateBookingPrice
{
    /**
     * Calculate applicable promotions and final price for a booking.
     *
     * @return array<string, mixed>
     */
    public function handle(CalculatePriceData $data): array
    {
        $pickupAt = Carbon::parse($data->pickupAt);
        $dropoffAt = Carbon::parse($data->dropoffAt);
        $rentalDays = (int) $pickupAt->diffInDays($dropoffAt);
        $rentalDays = max($rentalDays, 1);
        $dailyRate = $data->baseAmount / $rentalDays;

        $promotions = Promotion::query()
            ->with(['tiers', 'vehicleTiers', 'offices:id,code', 'vehicleCategories:id'])
            ->forSupplier($data->supplierId)
            ->active()
            ->validForDate($pickupAt->toDateString())
            ->where(function (Builder $query) use ($rentalDays): void {
                $query->whereNull('min_rental_days')
                    ->orWhere('min_rental_days', '<=', $rentalDays);
            })
            ->where('min_vehicle_count', '<=', $data->vehicleCount)
            ->where(function (Builder $query) use ($data): void {
                $query->where('applies_to_all_offices', true)
                    ->orWhereHas('offices', fn (Builder $q) => $q->where('code', $data->officeCode));
            })
            ->get();

        $seasonalPromotions = $promotions->where('type', 'seasonal')->where('discount_type', '!=', 'free_days');
        $volumePromotions = $promotions->where('type', 'volume');
        $vehicleVolumePromotions = $promotions->where('type', 'vehicle_volume');
        $freeDaysPromotions = $promotions->where('discount_type', 'free_days');

        $applicablePromotions = [];
        $totalDiscount = 0.0;
        $currentAmount = $data->baseAmount;

        // Seasonal (excluding free_days)
        $bestSeasonal = $this->resolveBestPromotion($seasonalPromotions, $currentAmount, $rentalDays);
        if ($bestSeasonal !== null) {
            $discount = $this->calculateDiscount($bestSeasonal, $currentAmount, $rentalDays);
            $totalDiscount += $discount;
            $currentAmount -= $discount;

            $seasonalPromotion = [
                'promotion_id' => $bestSeasonal->uuid,
                'name' => $bestSeasonal->name,
                'type' => 'seasonal',
                'discount_amount' => round($discount, 2),
                'discount_percentage' => $bestSeasonal->discount_type === 'percentage'
                    ? round((float) $bestSeasonal->discount_value, 2)
                    : round(($discount / $data->baseAmount) * 100, 2),
            ];

            if ($bestSeasonal->discount_type === 'daily_rate') {
                $matchedTier = $this->matchingDaysTier($bestSeasonal, $rentalDays);
                if ($matchedTier !== null) {
                    $seasonalPromotion['tier'] = [
                        'min_days' => $matchedTier->min_days,
                        'max_days' => $matchedTier->max_days,
                        'daily_rate' => (float) $matchedTier->discount_value,
                    ];
                }
            }

            $applicablePromotions[] = $seasonalPromotion;
        }

        // Volume (days)
        $bestVolume = $this->resolveBestVolumePromotion($volumePromotions, $rentalDays, $currentAmount);
        if ($bestVolume !== null) {
            $discount = $this->calculateDiscount($bestVolume['promotion'], $currentAmount, $rentalDays, $bestVolume['tier']);
            $totalDiscount += $discount;
            $currentAmount -= $discount;

            $applicablePromotions[] = [
                'promotion_id' => $bestVolume['promotion']->uuid,
                'name' => $bestVolume['promotion']->name,
                'type' => 'volume',
                'discount_amount' => round($discount, 2),
                'discount_percentage' => $bestVolume['promotion']->discount_type === 'percentage'
                    ? round((float) ($bestVolume['tier']?->discount_value ?? $bestVolume['promotion']->discount_value), 2)
                    : round(($discount / $data->baseAmount) * 100, 2),
                'tier' => $bestVolume['tier'] !== null ? [
                    'min_days' => $bestVolume['tier']->min_days,
                    'max_days' => $bestVolume['tier']->max_days,
                ] : null,
            ];
        }

        // Vehicle Volume
        $bestVehicleVolume = $this->resolveBestVehicleVolumePromotion($vehicleVolumePromotions, $data->vehicleCount, $currentAmount);
        if ($bestVehicleVolume !== null) {
            $discount = $this->calculateDiscount($bestVehicleVolume['promotion'], $currentAmount, $rentalDays, $bestVehicleVolume['tier']);
            $totalDiscount += $discount;
            $currentAmount -= $discount;

            $applicablePromotions[] = [
                'promotion_id' => $bestVehicleVolume['promotion']->uuid,
                'name' => $bestVehicleVolume['promotion']->name,
                'type' => 'vehicle_volume',
                'discount_amount' => round($discount, 2),
                'discount_percentage' => $bestVehicleVolume['promotion']->discount_type === 'percentage'
                    ? round((float) $bestVehicleVolume['tier']->discount_value, 2)
                    : round(($discount / $data->baseAmount) * 100, 2),
                'tier' => [
                    'min_vehicles' => $bestVehicleVolume['tier']->min_vehicles,
                    'max_vehicles' => $bestVehicleVolume['tier']->max_vehicles,
                ],
            ];
        }

        // Free days
        $bestFreeDays = $this->resolveBestFreeDaysPromotion($freeDaysPromotions, $rentalDays);
        if ($bestFreeDays !== null) {
            $discount = $this->calculateFreeDaysDiscount($bestFreeDays, $data->baseAmount, $rentalDays);
            $totalDiscount += $discount;
            $currentAmount -= $discount;

            $applicablePromotions[] = [
                'promotion_id' => $bestFreeDays->uuid,
                'name' => $bestFreeDays->name,
                'type' => 'free_days',
                'discount_amount' => round($discount, 2),
                'discount_percentage' => round(($discount / $data->baseAmount) * 100, 2),
                'free_days_granted' => (int) $bestFreeDays->free_days,
                'min_rental_days' => (int) $bestFreeDays->min_rental_days,
                'daily_rate' => round($dailyRate, 2),
                'paid_days' => $rentalDays - (int) $bestFreeDays->free_days,
            ];
        }

        return [
            'original_amount' => round($data->baseAmount, 2),
            'applicable_promotions' => $applicablePromotions,
            'total_discount' => round($totalDiscount, 2),
            'final_amount' => round(max($currentAmount, 0), 2),
            'currency' => $data->currency,
            'rental_days' => $rentalDays,
            'daily_rate' => round($dailyRate, 2),
        ];
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     */
    private function resolveBestPromotion(Collection $promotions, float $baseAmount, int $rentalDays): ?Promotion
    {
        if ($promotions->isEmpty()) {
            return null;
        }

        return $promotions
            ->sortByDesc(fn (Promotion $p) => $this->calculateDiscount($p, $baseAmount, $rentalDays))
            ->first();
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     * @return array{promotion: Promotion, tier: object|null, discount: float}|null
     */
    private function resolveBestVolumePromotion(Collection $promotions, int $rentalDays, float $baseAmount): ?array
    {
        $candidates = [];

        foreach ($promotions as $promotion) {
            if ($promotion->tiers->isEmpty()) {
                $discount = $this->calculateDiscount($promotion, $baseAmount, $rentalDays);
                $candidates[] = ['promotion' => $promotion, 'tier' => null, 'discount' => $discount];

                continue;
            }

            $tier = $promotion->tiers
                ->first(function ($t) use ($rentalDays) {
                    $min = (int) $t->min_days;
                    $max = $t->max_days !== null ? (int) $t->max_days : PHP_INT_MAX;

                    return $rentalDays >= $min && $rentalDays <= $max;
                });

            if ($tier === null) {
                continue;
            }

            $discount = $this->calculateDiscount($promotion, $baseAmount, $rentalDays, $tier);
            $candidates[] = ['promotion' => $promotion, 'tier' => $tier, 'discount' => $discount];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['discount'] <=> $a['discount']);

        return $candidates[0];
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     * @return array{promotion: Promotion, tier: object, discount: float}|null
     */
    private function resolveBestVehicleVolumePromotion(Collection $promotions, int $vehicleCount, float $baseAmount): ?array
    {
        $candidates = [];

        foreach ($promotions as $promotion) {
            $tier = $promotion->vehicleTiers
                ->first(function ($t) use ($vehicleCount) {
                    $min = (int) $t->min_vehicles;
                    $max = $t->max_vehicles !== null ? (int) $t->max_vehicles : PHP_INT_MAX;

                    return $vehicleCount >= $min && $vehicleCount <= $max;
                });

            if ($tier === null) {
                continue;
            }

            $discount = $this->calculateDiscount($promotion, $baseAmount, 1, $tier);
            $candidates[] = ['promotion' => $promotion, 'tier' => $tier, 'discount' => $discount];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['discount'] <=> $a['discount']);

        return $candidates[0];
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     */
    private function resolveBestFreeDaysPromotion(Collection $promotions, int $rentalDays): ?Promotion
    {
        if ($promotions->isEmpty()) {
            return null;
        }

        return $promotions
            ->where('min_rental_days', '<=', $rentalDays)
            ->sortByDesc('free_days')
            ->first();
    }

    /**
     * Calculate discount amount for a promotion.
     */
    private function calculateDiscount(Promotion $promotion, float $baseAmount, int $rentalDays, ?object $tier = null): float
    {
        if ($promotion->discount_type === 'daily_rate') {
            $tier ??= $this->matchingDaysTier($promotion, $rentalDays);

            if ($tier === null) {
                return 0.0;
            }

            return max($baseAmount - ((float) $tier->discount_value * $rentalDays), 0.0);
        }

        $discountValue = $tier !== null
            ? (float) $tier->discount_value
            : (float) $promotion->discount_value;

        if ($promotion->discount_type === 'percentage') {
            return $baseAmount * ($discountValue / 100);
        }

        return min($discountValue, $baseAmount);
    }

    private function calculateFreeDaysDiscount(Promotion $promotion, float $baseAmount, int $rentalDays): float
    {
        $dailyAmount = $baseAmount / max($rentalDays, 1);

        return min($dailyAmount * (int) $promotion->free_days, $baseAmount);
    }

    private function matchingDaysTier(Promotion $promotion, int $rentalDays): ?object
    {
        return $promotion->tiers
            ->first(function ($tier) use ($rentalDays): bool {
                $min = (int) $tier->min_days;
                $max = $tier->max_days !== null ? (int) $tier->max_days : PHP_INT_MAX;

                return $rentalDays >= $min && $rentalDays <= $max;
            });
    }
}
