<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'name', 'code', 'status', 'max_users', 'contact_name', 'email', 'phone'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

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
     * @param  \Illuminate\Database\Eloquent\Builder<Supplier>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Supplier>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by name, code or contact data.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Supplier>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Supplier>
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
