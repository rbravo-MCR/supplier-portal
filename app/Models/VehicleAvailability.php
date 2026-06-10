<?php

namespace App\Models;

use Database\Factories\VehicleAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
     * @param  Builder<VehicleAvailability>  $query
     * @return Builder<VehicleAvailability>
     */
    public function scopeForSupplier($query, ?int $supplierId)
    {
        if ($supplierId === null) {
            return $query;
        }

        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope a query to availabilities active on a given date.
     *
     * @param  Builder<VehicleAvailability>  $query
     * @return Builder<VehicleAvailability>
     */
    public function scopeForDate($query, string $date)
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->whereRaw("daterange(valid_from, valid_to, '[]') @> ?::date", [$date]);
        }

        return $query->where('valid_from', '<=', $date)
            ->where('valid_to', '>=', $date);
    }

    /**
     * Scope a query to filter by location code, acriss code or vehicle class.
     *
     * @param  Builder<VehicleAvailability>  $query
     * @return Builder<VehicleAvailability>
     */
    public function scopeSearch($query, string $term)
    {
        $op = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $upper = str($term)->upper()->toString();

        return $query->where(function ($query) use ($term, $upper, $op) {
            $query->where('location_code', $op, "{$upper}%")
                ->orWhere('acriss_code', $op, "{$upper}%")
                ->orWhere('vehicle_class', $op, "%{$term}%");
        });
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
