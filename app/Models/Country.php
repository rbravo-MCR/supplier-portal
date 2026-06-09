<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'name', 'iso2', 'iso3', 'status'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Country $country) {
            if (empty($country->uuid)) {
                $country->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (Country $country): void {
            Cache::forget('countries.active');
            Cache::forget('filter.countries.active');
            Cache::forget('default.country.mx');
            Cache::forget("selected.country.{$country->id}");
            Cache::forget("selected.filter.country.{$country->id}");
            Cache::forget("selected.country.iso2.{$country->id}");
            Cache::forget("selected.filter.country.iso2.{$country->id}");
        });

        static::deleted(function (Country $country): void {
            Cache::forget('countries.active');
            Cache::forget('filter.countries.active');
            Cache::forget('default.country.mx');
            Cache::forget("selected.country.{$country->id}");
            Cache::forget("selected.filter.country.{$country->id}");
            Cache::forget("selected.country.iso2.{$country->id}");
            Cache::forget("selected.filter.country.iso2.{$country->id}");
        });
    }

    /**
     * Get cities in this country.
     *
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include active countries.
     *
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by name, iso2 or iso3.
     *
     * @param  Builder<Country>  $query
     * @return Builder<Country>
     */
    public function scopeSearch($query, string $term)
    {
        $upper = str($term)->upper()->toString();
        $op = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function ($query) use ($term, $upper, $op) {
            $query->where('name', $op, "%{$term}%")
                ->orWhere('iso2', 'like', "{$upper}%")
                ->orWhere('iso3', 'like', "{$upper}%");
        });
    }
}
