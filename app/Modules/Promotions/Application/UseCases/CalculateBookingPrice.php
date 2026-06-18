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

        $promotions = Promotion::query()
            ->with(['tiers', 'offices:id,code', 'vehicleCategories:id'])
            ->forSupplier($data->supplierId)
            ->active()
            ->validForDate($pickupAt->toDateString())
            ->where(function (Builder $query) use ($data): void {
                $query->where('applies_to_all_offices', true)
                    ->orWhereHas('offices', fn (Builder $q) => $q->where('code', $data->officeCode));
            })
            ->get();

        $seasonalPromotions = $promotions->where('type', 'seasonal');
        $volumePromotions = $promotions->where('type', 'volume');

        $applicablePromotions = [];
        $totalDiscount = 0.0;
        $currentAmount = $data->baseAmount;

        $bestSeasonal = $this->resolveBestPromotion($seasonalPromotions, $currentAmount);
        if ($bestSeasonal !== null) {
            $discount = $this->calculateDiscount($bestSeasonal, $currentAmount);
            $totalDiscount += $discount;
            $currentAmount -= $discount;

            $applicablePromotions[] = [
                'promotion_id' => $bestSeasonal->uuid,
                'name' => $bestSeasonal->name,
                'type' => 'seasonal',
                'discount_amount' => round($discount, 2),
                'discount_percentage' => $bestSeasonal->discount_type === 'percentage'
                    ? round((float) $bestSeasonal->discount_value, 2)
                    : round(($discount / $data->baseAmount) * 100, 2),
            ];
        }

        $bestVolume = $this->resolveBestVolumePromotion($volumePromotions, $rentalDays, $currentAmount);
        if ($bestVolume !== null) {
            $discount = $this->calculateDiscount($bestVolume['promotion'], $currentAmount, $bestVolume['tier']);
            $totalDiscount += $discount;
            $currentAmount -= $discount;

            $applicablePromotions[] = [
                'promotion_id' => $bestVolume['promotion']->uuid,
                'name' => $bestVolume['promotion']->name,
                'type' => 'volume',
                'discount_amount' => round($discount, 2),
                'discount_percentage' => $bestVolume['promotion']->discount_type === 'percentage'
                    ? round((float) $bestVolume['tier']['discount_value'], 2)
                    : round(($discount / $data->baseAmount) * 100, 2),
                'tier' => [
                    'min_days' => $bestVolume['tier']['min_days'],
                    'max_days' => $bestVolume['tier']['max_days'],
                ],
            ];
        }

        return [
            'original_amount' => round($data->baseAmount, 2),
            'applicable_promotions' => $applicablePromotions,
            'total_discount' => round($totalDiscount, 2),
            'final_amount' => round(max($currentAmount, 0), 2),
            'currency' => $data->currency,
            'rental_days' => $rentalDays,
        ];
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     */
    private function resolveBestPromotion(Collection $promotions, float $baseAmount): ?Promotion
    {
        if ($promotions->isEmpty()) {
            return null;
        }

        return $promotions
            ->sortByDesc(fn (Promotion $p) => $this->calculateDiscount($p, $baseAmount))
            ->first();
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     * @return array{promotion: Promotion, tier: array<string, mixed>}|null
     */
    private function resolveBestVolumePromotion(Collection $promotions, int $rentalDays, float $baseAmount): ?array
    {
        $candidates = [];

        foreach ($promotions as $promotion) {
            $tier = $promotion->tiers
                ->first(function ($t) use ($rentalDays) {
                    $min = (int) $t->min_days;
                    $max = $t->max_days !== null ? (int) $t->max_days : PHP_INT_MAX;

                    return $rentalDays >= $min && $rentalDays <= $max;
                });

            if ($tier === null) {
                continue;
            }

            $discount = $this->calculateDiscount($promotion, $baseAmount, $tier);
            $candidates[] = ['promotion' => $promotion, 'tier' => $tier, 'discount' => $discount];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['discount'] <=> $a['discount']);

        return $candidates[0];
    }

    /**
     * Calculate discount amount for a promotion.
     *
     * @param  array<string, mixed>|null  $tier
     */
    private function calculateDiscount(Promotion $promotion, float $baseAmount, ?object $tier = null): float
    {
        $discountValue = $tier !== null
            ? (float) $tier->discount_value
            : (float) $promotion->discount_value;

        if ($promotion->discount_type === 'percentage') {
            return $baseAmount * ($discountValue / 100);
        }

        return min($discountValue, $baseAmount);
    }
}
