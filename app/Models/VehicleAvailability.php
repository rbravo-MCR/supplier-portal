<?php

namespace App\Models;

use Database\Factories\VehicleAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'supplier_id', 'office_id', 'location_type', 'location_code', 'office_code', 'iata_code', 'vehicle_class', 'acriss_code', 'available_quantity', 'valid_from', 'valid_to', 'status', 'metadata'])]
class VehicleAvailability extends Model
{
    /** @use HasFactory<VehicleAvailabilityFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VehicleAvailability $vehicleAvailability) {
            if (empty($vehicleAvailability->uuid)) {
                $vehicleAvailability->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the supplier that owns this availability window.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the catalog office matched to this availability window.
     *
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Scope a query to only include availabilities for a given supplier.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<VehicleAvailability>  $query
     * @return \Illuminate\Database\Eloquent\Builder<VehicleAvailability>
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_quantity' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'metadata' => 'array',
        ];
    }
}
