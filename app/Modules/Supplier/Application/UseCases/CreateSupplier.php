<?php

namespace App\Modules\Supplier\Application\UseCases;

use App\Jobs\RecordAuditLog;
use App\Models\Country;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Supplier\Application\Contracts\SupplierRepository;
use App\Modules\Supplier\Application\DTOs\CreateSupplierData;
use App\Modules\Supplier\Domain\Exceptions\SupplierCodeAlreadyExists;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSupplier
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly SupplierRepository $suppliers,
    ) {}

    /**
     * Create an active supplier and audit the action.
     */
    public function handle(CreateSupplierData $data, User $actor): Supplier
    {
        $normalizedCode = Str::of($data->code)->trim()->upper()->toString();

        if ($this->suppliers->existsByCode($normalizedCode)) {
            throw SupplierCodeAlreadyExists::forCode($normalizedCode);
        }

        $countryIso2 = Country::query()
            ->whereKey($data->countryId)
            ->where('status', 'active')
            ->value('iso2');

        if ($countryIso2 === null || $countryIso2 === 'MX') {
            throw ValidationException::withMessages([
                'countryId' => __('Solo se pueden dar de alta proveedores fuera de México.'),
            ]);
        }

        if ($data->integrationType !== Supplier::IntegrationNone) {
            throw ValidationException::withMessages([
                'integrationType' => __('Solo se pueden dar de alta proveedores sin API ni SOAP.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $data, $normalizedCode): Supplier {
            $supplier = $this->suppliers->create($data, $normalizedCode);

            dispatch(new RecordAuditLog([
                'supplier_id' => $supplier->id,
                'user_id' => $actor->id,
                'module' => 'supplier',
                'action' => 'created',
                'entity_type' => Supplier::class,
                'entity_id' => $supplier->id,
                'old_values' => null,
                'new_values' => [
                    'name' => $supplier->name,
                    'code' => $supplier->code,
                    'country_id' => $supplier->country_id,
                    'integration_type' => $supplier->integration_type,
                    'status' => $supplier->status,
                    'max_users' => $supplier->max_users,
                ],
            ]));

            return $supplier;
        });
    }
}
