<?php

namespace App\Modules\Pricing\Infrastructure\Repositories;

use App\Models\Currency;
use App\Models\Rate;
use App\Modules\Pricing\Application\Contracts\RateRepository;
use App\Modules\Pricing\Application\DTOs\PublishRateData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EloquentRateRepository implements RateRepository
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * List active rates for a supplier.
     */
    public function activeForSupplier(int $supplierId, int $perPage): LengthAwarePaginator
    {
        return Rate::query()
            ->with(['supplier', 'currency'])
            ->forSupplier($supplierId)
            ->active()
            ->orderByDesc('valid_from')
            ->paginate($perPage);
    }

    /**
     * Determine whether the proposed validity overlaps an active rate.
     */
    public function hasActiveOverlap(int $supplierId, PublishRateData $data): bool
    {
        $cacheKey = "rate.overlap.{$supplierId}.{$data->officeCode}.{$data->acrissCode}.{$data->ratePlanCode}.{$data->validFrom}.{$data->validTo}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($supplierId, $data): bool {
            return Rate::query()
                ->forSupplier($supplierId)
                ->where('office_code', $data->officeCode)
                ->where('acriss_code', $data->acrissCode)
                ->where('rate_plan_code', $data->ratePlanCode)
                ->active()
                ->whereDate('valid_from', '<=', $data->validTo)
                ->whereDate('valid_to', '>=', $data->validFrom)
                ->exists();
        });
    }

    /**
     * Get the next version for a supplier rate key.
     */
    public function nextVersion(int $supplierId, PublishRateData $data): int
    {
        $cacheKey = "rate.version.{$supplierId}.{$data->officeCode}.{$data->acrissCode}.{$data->ratePlanCode}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($supplierId, $data): int {
            $latestVersion = Rate::query()
                ->forSupplier($supplierId)
                ->where('office_code', $data->officeCode)
                ->where('acriss_code', $data->acrissCode)
                ->where('rate_plan_code', $data->ratePlanCode)
                ->max('version');

            return ((int) $latestVersion) + 1;
        });
    }

    /**
     * Persist a rate.
     */
    public function create(int $supplierId, PublishRateData $data, int $version): Rate
    {
        $currency = Currency::query()
            ->active()
            ->where('code', Str::upper($data->currency))
            ->firstOrFail();

        $rate = Rate::query()->create([
            'supplier_id' => $supplierId,
            'office_code' => $data->officeCode,
            'vehicle_class' => $data->vehicleClass,
            'acriss_code' => $data->acrissCode,
            'rate_plan_code' => $data->ratePlanCode,
            'currency_id' => $currency->id,
            'base_price' => $data->basePrice,
            'valid_from' => $data->validFrom,
            'valid_to' => $data->validTo,
            'min_days' => $data->minDays,
            'max_days' => $data->maxDays,
            'status' => 'active',
            'version' => $version,
            'created_by' => $data->createdBy,
        ]);

        $this->clearRateCache($supplierId, $data);

        return $rate;
    }

    /**
     * Clear cached rate queries for a supplier key.
     */
    private function clearRateCache(int $supplierId, PublishRateData $data): void
    {
        $overlapKey = "rate.overlap.{$supplierId}.{$data->officeCode}.{$data->acrissCode}.{$data->ratePlanCode}.{$data->validFrom}.{$data->validTo}";
        $versionKey = "rate.version.{$supplierId}.{$data->officeCode}.{$data->acrissCode}.{$data->ratePlanCode}";

        Cache::forget($overlapKey);
        Cache::forget($versionKey);
    }
}
