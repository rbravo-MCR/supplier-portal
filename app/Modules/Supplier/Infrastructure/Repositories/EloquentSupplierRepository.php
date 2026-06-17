<?php

namespace App\Modules\Supplier\Infrastructure\Repositories;

use App\Models\Supplier;
use App\Modules\Supplier\Application\Contracts\SupplierRepository;
use App\Modules\Supplier\Application\DTOs\CreateSupplierData;

class EloquentSupplierRepository implements SupplierRepository
{
    /**
     * Determine whether a supplier code already exists.
     */
    public function existsByCode(string $code): bool
    {
        return Supplier::query()
            ->where('code', $code)
            ->exists();
    }

    /**
     * Persist a supplier.
     */
    public function create(CreateSupplierData $data, string $normalizedCode): Supplier
    {
        return Supplier::query()->create([
            'name' => $data->name,
            'code' => $normalizedCode,
            'country_id' => $data->countryId,
            'integration_type' => $data->integrationType,
            'status' => $data->status,
            'max_users' => $data->maxUsers,
            'contact_name' => $data->contactName,
            'email' => $data->email,
            'phone' => $data->phone,
        ]);
    }
}
