<?php

namespace App\Models;

use Database\Factories\VehicleCategoryCatalogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name_es', 'name_en', 'vehicle_body_type', 'passenger_capacity_min', 'passenger_capacity_max', 'category_family', 'transmission_type', 'fuel_type', 'variant_key', 'status'])]
class VehicleCategoryCatalog extends Model
{
    /** @use HasFactory<VehicleCategoryCatalogFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the ACRISS codes assigned to this catalog category.
     *
     * @return HasMany<VehicleCategoryAcrissCode, $this>
     */
    public function acrissCodes(): HasMany
    {
        return $this->hasMany(VehicleCategoryAcrissCode::class);
    }

    /**
     * Get supplier category mappings that use this catalog category.
     *
     * @return HasMany<VehicleCategory, $this>
     */
    public function vehicleCategories(): HasMany
    {
        return $this->hasMany(VehicleCategory::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passenger_capacity_min' => 'integer',
            'passenger_capacity_max' => 'integer',
        ];
    }
}
