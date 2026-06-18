<?php

namespace App\Modules\Promotions\Application\UseCases;

use App\Jobs\DispatchOutboxEvent;
use App\Jobs\RecordAuditLog;
use App\Models\Promotion;
use App\Modules\Promotions\Application\Contracts\PromotionRepository;
use App\Modules\Promotions\Application\DTOs\CreatePromotionData;
use App\Modules\Promotions\Domain\Exceptions\PromotionOverlapDetected;
use App\Modules\Promotions\Domain\Rules\PromotionDatesMustBeValid;
use App\Modules\Promotions\Domain\Rules\VolumeTierDaysMustBeValid;
use App\Shared\Support\SupplierContext;
use Illuminate\Support\Facades\DB;

class CreatePromotion
{
    public function __construct(
        private readonly PromotionRepository $promotions,
        private readonly SupplierContext $supplierContext,
        private readonly PromotionDatesMustBeValid $datesRule,
        private readonly VolumeTierDaysMustBeValid $tiersRule,
    ) {}

    public function handle(CreatePromotionData $data): Promotion
    {
        $this->datesRule->validate($data->validFrom, $data->validTo);

        if ($data->type === 'volume') {
            $this->tiersRule->validate($data->tiers);
        }

        $supplierId = $this->supplierContext->id();

        return DB::transaction(function () use ($data, $supplierId): Promotion {
            if ($this->promotions->hasActiveOverlap($supplierId, $data)) {
                throw $data->type === 'seasonal'
                    ? PromotionOverlapDetected::forSeasonal()
                    : PromotionOverlapDetected::forVolume();
            }

            $promotion = $this->promotions->create($supplierId, $data);

            dispatch(new RecordAuditLog([
                'supplier_id' => $supplierId,
                'user_id' => $data->createdBy,
                'module' => 'promotions',
                'action' => 'promotion_created',
                'entity_type' => Promotion::class,
                'entity_id' => $promotion->id,
                'old_values' => null,
                'new_values' => [
                    'name' => $promotion->name,
                    'type' => $promotion->type,
                    'discount_type' => $promotion->discount_type,
                    'discount_value' => $promotion->discount_value,
                    'valid_from' => $promotion->valid_from->toDateString(),
                    'valid_to' => $promotion->valid_to->toDateString(),
                ],
            ]))->afterCommit();

            dispatch(new DispatchOutboxEvent([
                'aggregate_type' => Promotion::class,
                'aggregate_id' => $promotion->id,
                'event_type' => 'PromotionCreated',
                'payload' => [
                    'supplier_id' => $supplierId,
                    'promotion_uuid' => $promotion->uuid,
                    'type' => $promotion->type,
                    'occurred_at' => now()->toISOString(),
                ],
                'status' => 'pending',
                'available_at' => now(),
            ]))->afterCommit();

            return $promotion;
        });
    }
}
