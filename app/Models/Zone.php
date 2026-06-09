<?php

namespace App\Models;

use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'city_id', 'name', 'code', 'status'])]
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Zone $zone) {
            if (empty($zone->uuid)) {
                $zone->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (Zone $zone): void {
            $countryId = $zone->city?->country_id;
            Cache::forget("zones.active.{$countryId}.{$zone->city_id}");
            Cache::forget("filter.zones.{$countryId}.{$zone->city_id}");
            Cache::forget("selected.zone.{$zone->id}");
            Cache::forget("selected.filter.zone.{$zone->id}");
        });

        static::deleted(function (Zone $zone): void {
            $countryId = $zone->city?->country_id;
            Cache::forget("zones.active.{$countryId}.{$zone->city_id}");
            Cache::forget("filter.zones.{$countryId}.{$zone->city_id}");
            Cache::forget("selected.zone.{$zone->id}");
            Cache::forget("selected.filter.zone.{$zone->id}");
        });
    }

    /**
     * Get the city that owns this zone.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get offices in this zone.
     *
     * @return HasMany<Office, $this>
     */
    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include active zones.
     *
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include zones for a given city.
     *
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeForCity($query, ?int $cityId)
    {
        if ($cityId === null) {
            return $query;
        }

        return $query->where('city_id', $cityId);
    }

    /**
     * Scope a query to only include zones for cities in a given country.
     *
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeForCountry($query, ?int $countryId)
    {
        if ($countryId === null) {
            return $query;
        }

        return $query->whereIn('city_id', City::query()
            ->select('id')
            ->where('country_id', $countryId)
        );
    }

    /**
     * Scope a query to filter by name or code.
     *
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeSearch($query, string $term)
    {
        $op = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function ($query) use ($term, $op) {
            $query->where('name', $op, "%{$term}%")
                ->orWhere('code', $op, "{$term}%");
        });
    }
}
