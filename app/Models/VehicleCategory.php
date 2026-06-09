<?php

namespace App\Models;

use Database\Factories\VehicleCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'vehicle_category_catalog_id', 'supplier_code', 'name', 'code', 'acriss_prefix', 'description', 'status'])]
class VehicleCategory extends Model
{
    /** @use HasFactory<VehicleCategoryFactory> */
    use HasFactory;

    /**
     * Get the supplier that owns the category.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the master catalog category mapped to this supplier category.
     *
     * @return BelongsTo<VehicleCategoryCatalog, $this>
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(VehicleCategoryCatalog::class, 'vehicle_category_catalog_id');
    }

    /**
     * Scope by supplier.
     */
    public function scopeForSupplier(Builder $query, ?int $supplierId): Builder
    {
        return $query->when($supplierId, fn (Builder $query): Builder => $query->where('supplier_id', $supplierId));
    }
}
