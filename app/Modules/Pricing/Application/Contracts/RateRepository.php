<?php

namespace App\Modules\Pricing\Application\Contracts;

use App\Models\Rate;
use App\Modules\Pricing\Application\DTOs\PublishRateData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RateRepository
{
    /**
     * List active rates for a supplier.
     */
    public function activeForSupplier(int $supplierId, int $perPage): LengthAwarePaginator;

    /**
     * Determine whether the proposed validity overlaps an active rate.
     */
    public function hasActiveOverlap(int $supplierId, PublishRateData $data): bool;

    /**
     * Get the next version for a supplier rate key.
     */
    public function nextVersion(int $supplierId, PublishRateData $data): int;

    /**
     * Persist a rate.
     */
    public function create(int $supplierId, PublishRateData $data, int $version): Rate;
}
