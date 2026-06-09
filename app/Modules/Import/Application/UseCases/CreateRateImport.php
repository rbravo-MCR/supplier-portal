<?php

namespace App\Modules\Import\Application\UseCases;

use App\Jobs\DispatchOutboxEvent;
use App\Models\RateImport;
use App\Modules\Import\Application\Contracts\RateImportRepository;
use App\Modules\Import\Application\DTOs\CreateRateImportData;
use App\Modules\Import\Domain\Exceptions\RateImportCannotBeEmpty;
use App\Shared\Support\SupplierContext;

class CreateRateImport
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly RateImportRepository $rateImports,
        private readonly SupplierContext $supplierContext,
    ) {}

    /**
     * Create an import staging record for the authenticated supplier.
     */
    public function handle(CreateRateImportData $data): RateImport
    {
        if ($data->rows === []) {
            throw RateImportCannotBeEmpty::make();
        }

        $rateImport = $this->rateImports->createWithRows(
            supplierId: $this->supplierContext->id(),
            data: $data,
        );

        dispatch(new DispatchOutboxEvent([
            'aggregate_type' => RateImport::class,
            'aggregate_id' => $rateImport->id,
            'event_type' => 'RateImportUploaded',
            'payload' => [
                'rate_import_uuid' => $rateImport->uuid,
                'supplier_id' => $rateImport->supplier_id,
                'uploaded_by' => $data->uploadedBy,
                'occurred_at' => now()->toISOString(),
            ],
            'status' => 'pending',
            'available_at' => now(),
        ]));

        return $rateImport;
    }
}
