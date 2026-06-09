<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'country_id', 'name', 'code', 'status'])]
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (City $city) {
            if (empty($city->uuid)) {
                $city->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (City $city): void {
            Cache::forget("cities.active.{$city->country_id}");
            Cache::forget("filter.cities.{$city->country_id}");
            Cache::forget("selected.city.{$city->id}");
            Cache::forget("selected.filter.city.{$city->id}");
        });

        static::deleted(function (City $city): void {
            Cache::forget("cities.active.{$city->country_id}");
            Cache::forget("filter.cities.{$city->country_id}");
            Cache::forget("selected.city.{$city->id}");
            Cache::forget("selected.filter.city.{$city->id}");
        });
    }

    /**
     * Get the country that owns this city.
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get zones in this city.
     *
     * @return HasMany<Zone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include active cities.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<City>  $query
     * @return \Illuminate\Database\Eloquent\Builder<City>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include cities for a given country.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<City>  $query
     * @return \Illuminate\Database\Eloquent\Builder<City>
     */
    public function scopeForCountry($query, ?int $countryId)
    {
        if ($countryId === null) {
            return $query;
        }

        return $query->where('country_id', $countryId);
    }

    /**
     * Scope a query to filter by name or code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<City>  $query
     * @return \Illuminate\Database\Eloquent\Builder<City>
     */
    public function scopeSearch($query, string $term)
    {
        $term = str($term)->upper()->toString();

        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }
}
