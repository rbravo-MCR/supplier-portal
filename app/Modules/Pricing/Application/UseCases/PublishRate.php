<?php

namespace App\Modules\Pricing\Application\UseCases;

use App\Jobs\DispatchOutboxEvent;
use App\Jobs\RecordAuditLog;
use App\Models\Rate;
use App\Modules\Pricing\Application\Contracts\RateRepository;
use App\Modules\Pricing\Application\DTOs\PublishRateData;
use App\Modules\Pricing\Domain\Exceptions\RateOverlapDetected;
use App\Modules\Pricing\Domain\Rules\PriceMustBeGreaterThanZero;
use App\Modules\Pricing\Domain\Rules\RateValidityDatesMustBeValid;
use App\Shared\Support\SupplierContext;
use Illuminate\Support\Facades\DB;

class PublishRate
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly RateRepository $rates,
        private readonly SupplierContext $supplierContext,
        private readonly PriceMustBeGreaterThanZero $priceRule,
        private readonly RateValidityDatesMustBeValid $validityRule,
    ) {}

    /**
     * Publish a new active rate version for the authenticated supplier.
     */
    public function handle(PublishRateData $data): Rate
    {
        $this->priceRule->validate($data->basePrice);
        $this->validityRule->validate($data->validFrom, $data->validTo);

        $supplierId = $this->supplierContext->id();

        return DB::transaction(function () use ($data, $supplierId): Rate {
            if ($this->rates->hasActiveOverlap($supplierId, $data)) {
                throw RateOverlapDetected::forRateKey();
            }

            $rate = $this->rates->create(
                supplierId: $supplierId,
                data: $data,
                version: $this->rates->nextVersion($supplierId, $data),
            );

            dispatch(new RecordAuditLog([
                'supplier_id' => $supplierId,
                'user_id' => $data->createdBy,
                'module' => 'pricing',
                'action' => 'rate_published',
                'entity_type' => Rate::class,
                'entity_id' => $rate->id,
                'old_values' => null,
                'new_values' => [
                    'office_code' => $rate->office_code,
                    'acriss_code' => $rate->acriss_code,
                    'rate_plan_code' => $rate->rate_plan_code,
                    'base_price' => $rate->base_price,
                    'version' => $rate->version,
                ],
            ]))->afterCommit();

            dispatch(new DispatchOutboxEvent([
                'aggregate_type' => Rate::class,
                'aggregate_id' => $rate->id,
                'event_type' => 'RatesPublished',
                'payload' => [
                    'supplier_id' => $supplierId,
                    'rate_plan_code' => $rate->rate_plan_code,
                    'version' => $rate->version,
                    'occurred_at' => now()->toISOString(),
                ],
                'status' => 'pending',
                'available_at' => now(),
            ]))->afterCommit();

            return $rate;
        });
    }
}
