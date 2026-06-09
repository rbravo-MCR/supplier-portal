<?php

namespace App\Models;

use Database\Factories\VehicleCategoryAcrissCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_category_catalog_id', 'code'])]
class VehicleCategoryAcrissCode extends Model
{
    /** @use HasFactory<VehicleCategoryAcrissCodeFactory> */
    use HasFactory;

    /**
     * Get the catalog category for this ACRISS code.
     *
     * @return BelongsTo<VehicleCategoryCatalog, $this>
     */
    public function vehicleCategoryCatalog(): BelongsTo
    {
        return $this->belongsTo(VehicleCategoryCatalog::class);
    }
}
