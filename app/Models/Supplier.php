<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'name', 'code', 'country_id', 'integration_type', 'status', 'max_users', 'contact_name', 'email', 'phone'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    public const IntegrationNone = 'none';

    public const IntegrationApi = 'api';

    public const IntegrationSoap = 'soap';

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Supplier $supplier) {
            if (empty($supplier->uuid)) {
                $supplier->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (): void {
            Cache::forget('suppliers.active');
        });

        static::deleted(function (): void {
            Cache::forget('suppliers.active');
        });
    }

    /**
     * Get users assigned to the supplier.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the country where the supplier is legally based.
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get vehicle categories configured for the supplier.
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
            'max_users' => 'integer',
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
     * Scope a query to only include active suppliers.
     *
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by name, code or contact data.
     *
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeSearch($query, string $term)
    {
        $isPostgres = $query->getConnection()->getDriverName() === 'pgsql';
        $op = $isPostgres ? 'ilike' : 'like';

        return $query->where(function ($query) use ($term, $op) {
            $query->where('name', $op, "%{$term}%")
                ->orWhere('code', $op, "%{$term}%")
                ->orWhere('contact_name', $op, "%{$term}%")
                ->orWhere('email', $op, "%{$term}%");
        });
    }
}
