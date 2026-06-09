<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'zone_id', 'supplier_id', 'name', 'code', 'iata_code', 'type', 'status', 'address', 'latitude', 'longitude'])]
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Office $office) {
            if (empty($office->uuid)) {
                $office->uuid = (string) Str::uuid();
            }
        });

    }

    /**
     * Get the zone that owns this office.
     *
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Get the supplier assigned to this office.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get availability windows attached to this office.
     *
     * @return HasMany<VehicleAvailability, $this>
     */
    public function vehicleAvailabilities(): HasMany
    {
        return $this->hasMany(VehicleAvailability::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include active offices.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Office>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Office>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include offices for a given supplier.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Office>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Office>
     */
    public function scopeForSupplier($query, ?int $supplierId)
    {
        if ($supplierId === null) {
            return $query;
        }

        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope a query to filter by name or code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Office>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Office>
     */
    public function scopeSearch($query, string $term)
    {
        $term = str($term)->upper()->toString();

        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('iata_code', 'like', "%{$term}%");
        });
    }
}
