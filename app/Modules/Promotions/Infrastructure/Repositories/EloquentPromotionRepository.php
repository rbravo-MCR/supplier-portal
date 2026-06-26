<?php

namespace App\Modules\Promotions\Infrastructure\Repositories;

use App\Models\Promotion;
use App\Modules\Promotions\Application\Contracts\PromotionRepository;
use App\Modules\Promotions\Application\DTOs\CreatePromotionData;
use App\Modules\Promotions\Application\DTOs\ListPromotionsFilterData;
use App\Modules\Promotions\Application\DTOs\UpdatePromotionData;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentPromotionRepository implements PromotionRepository
{
    public function listForSupplier(?int $supplierId, ListPromotionsFilterData $filter): Paginator
    {
        return Promotion::query()
            ->with(['offices:id,name,code', 'vehicleCategories:id,name,code', 'tiers', 'vehicleTiers'])
            ->when($supplierId !== null, fn (Builder $query) => $query->forSupplier($supplierId))
            ->when($filter->type !== null, fn (Builder $query) => $query->where('type', $filter->type))
            ->when($filter->status !== null, fn (Builder $query) => $query->where('status', $filter->status))
            ->when($filter->search !== null && $filter->search !== '', fn (Builder $query) => $query->search($filter->search))
            ->orderByDesc('valid_from')
            ->simplePaginate($filter->perPage);
    }

    public function hasActiveOverlap(int $supplierId, CreatePromotionData $data, ?string $excludeUuid = null): bool
    {
        $query = Promotion::query()
            ->forSupplier($supplierId)
            ->where('type', $data->type)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($data): void {
                $query->whereBetween('valid_from', [$data->validFrom, $data->validTo])
                    ->orWhereBetween('valid_to', [$data->validFrom, $data->validTo])
                    ->orWhere(function (Builder $query) use ($data): void {
                        $query->where('valid_from', '<=', $data->validFrom)
                            ->where('valid_to', '>=', $data->validTo);
                    });
            });

        if ($excludeUuid !== null) {
            $query->where('uuid', '!=', $excludeUuid);
        }

        return $query->exists();
    }

    public function create(int $supplierId, CreatePromotionData $data): Promotion
    {
        $promotion = Promotion::query()->create([
            'supplier_id' => $supplierId,
            'name' => $data->name,
            'type' => $data->type,
            'discount_type' => $data->discountType,
            'discount_value' => $data->discountValue,
            'min_rental_days' => $data->minRentalDays,
            'free_days' => $data->freeDays,
            'min_vehicle_count' => $data->minVehicleCount,
            'valid_from' => $data->validFrom,
            'valid_to' => $data->validTo,
            'status' => $data->status,
            'applies_to_all_offices' => $data->appliesToAllOffices,
            'applies_to_all_categories' => $data->appliesToAllCategories,
            'created_by' => $data->createdBy,
        ]);

        if (! $data->appliesToAllOffices && ! empty($data->officeIds)) {
            $promotion->offices()->sync($data->officeIds);
        }

        if (! $data->appliesToAllCategories && ! empty($data->categoryIds)) {
            $promotion->vehicleCategories()->sync($data->categoryIds);
        }

        if ($data->type === 'volume' && ! empty($data->tiers)) {
            foreach ($data->tiers as $tier) {
                $promotion->tiers()->create([
                    'min_days' => $tier['min_days'],
                    'max_days' => $tier['max_days'] ?? null,
                    'discount_value' => $tier['discount_value'],
                ]);
            }
        }

        if ($data->type === 'vehicle_volume' && $data->vehicleTiers !== null && ! empty($data->vehicleTiers)) {
            foreach ($data->vehicleTiers as $tier) {
                $promotion->vehicleTiers()->create([
                    'min_vehicles' => $tier['min_vehicles'],
                    'max_vehicles' => $tier['max_vehicles'] ?? null,
                    'discount_value' => $tier['discount_value'],
                ]);
            }
        }

        return $promotion->fresh(['offices', 'vehicleCategories', 'tiers', 'vehicleTiers']);
    }

    public function update(Promotion $promotion, UpdatePromotionData $data): Promotion
    {
        $fields = [];

        if ($data->name !== null) {
            $fields['name'] = $data->name;
        }
        if ($data->discountType !== null) {
            $fields['discount_type'] = $data->discountType;
        }
        if ($data->minRentalDays !== null) {
            $fields['min_rental_days'] = $data->minRentalDays;
        }
        if ($data->freeDays !== null) {
            $fields['free_days'] = $data->freeDays;
        }
        if ($data->minVehicleCount !== null) {
            $fields['min_vehicle_count'] = $data->minVehicleCount;
        }
        if ($data->discountValue !== null) {
            $fields['discount_value'] = $data->discountValue;
        }
        if ($data->validFrom !== null) {
            $fields['valid_from'] = $data->validFrom;
        }
        if ($data->validTo !== null) {
            $fields['valid_to'] = $data->validTo;
        }
        if ($data->status !== null) {
            $fields['status'] = $data->status;
        }
        if ($data->appliesToAllOffices !== null) {
            $fields['applies_to_all_offices'] = $data->appliesToAllOffices;
        }
        if ($data->appliesToAllCategories !== null) {
            $fields['applies_to_all_categories'] = $data->appliesToAllCategories;
        }

        if (! empty($fields)) {
            $promotion->update($fields);
        }

        if ($data->appliesToAllOffices !== null) {
            if ($data->appliesToAllOffices) {
                $promotion->offices()->detach();
            } elseif ($data->officeIds !== null) {
                $promotion->offices()->sync($data->officeIds);
            }
        } elseif ($data->officeIds !== null && ! $promotion->applies_to_all_offices) {
            $promotion->offices()->sync($data->officeIds);
        }

        if ($data->appliesToAllCategories !== null) {
            if ($data->appliesToAllCategories) {
                $promotion->vehicleCategories()->detach();
            } elseif ($data->categoryIds !== null) {
                $promotion->vehicleCategories()->sync($data->categoryIds);
            }
        } elseif ($data->categoryIds !== null && ! $promotion->applies_to_all_categories) {
            $promotion->vehicleCategories()->sync($data->categoryIds);
        }

        if ($promotion->type === 'volume' && $data->tiers !== null) {
            $promotion->tiers()->delete();
            foreach ($data->tiers as $tier) {
                $promotion->tiers()->create([
                    'min_days' => $tier['min_days'],
                    'max_days' => $tier['max_days'] ?? null,
                    'discount_value' => $tier['discount_value'],
                ]);
            }
        }

        if ($promotion->type === 'vehicle_volume' && $data->vehicleTiers !== null) {
            $promotion->vehicleTiers()->delete();
            foreach ($data->vehicleTiers as $tier) {
                $promotion->vehicleTiers()->create([
                    'min_vehicles' => $tier['min_vehicles'],
                    'max_vehicles' => $tier['max_vehicles'] ?? null,
                    'discount_value' => $tier['discount_value'],
                ]);
            }
        }

        return $promotion->fresh(['offices', 'vehicleCategories', 'tiers', 'vehicleTiers']);
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->offices()->detach();
        $promotion->vehicleCategories()->detach();
        $promotion->tiers()->delete();
        $promotion->vehicleTiers()->delete();
        $promotion->delete();
    }
}
