<?php

namespace App\Modules\Pricing\Application\UseCases;

use App\Modules\Pricing\Application\Contracts\RateRepository;
use App\Shared\Support\SupplierContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListRates
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly RateRepository $rates,
        private readonly SupplierContext $supplierContext,
    ) {}

    /**
     * List active rates for the authenticated supplier.
     */
    public function handle(int $perPage = 15): LengthAwarePaginator
    {
        return $this->rates->activeForSupplier($this->supplierContext->id(), $perPage);
    }
}
