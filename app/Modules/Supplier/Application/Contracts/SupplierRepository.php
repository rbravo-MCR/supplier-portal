<?php

namespace App\Modules\Supplier\Application\Contracts;

use App\Models\Supplier;
use App\Modules\Supplier\Application\DTOs\CreateSupplierData;

interface SupplierRepository
{
    /**
     * Determine whether a supplier code already exists.
     */
    public function existsByCode(string $code): bool;

    /**
     * Persist a supplier.
     */
    public function create(CreateSupplierData $data, string $normalizedCode): Supplier;
}
