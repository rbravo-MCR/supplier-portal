<?php

namespace App\Models;

use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'supplier_id', 'name', 'type', 'discount_type', 'discount_value', 'min_rental_days', 'free_days', 'min_vehicle_count', 'valid_from', 'valid_to', 'status', 'applies_to_all_offices', 'applies_to_all_categories', 'created_by'])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Promotion $promotion) {
            if (empty($promotion->uuid)) {
                $promotion->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the supplier that owns the promotion.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the actor that created the promotion.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the offices where this promotion applies.
     *
     * @return BelongsToMany<Office, $this>
     */
    public function offices(): BelongsToMany
    {
        return $this->belongsToMany(Office::class, 'promotion_office');
    }

    /**
     * Get the vehicle categories where this promotion applies.
     *
     * @return BelongsToMany<VehicleCategory, $this>
     */
    public function vehicleCategories(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCategory::class, 'promotion_category');
    }

    /**
     * Get the volume discount tiers for this promotion.
     *
     * @return HasMany<PromotionDiscountTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(PromotionDiscountTier::class)->orderBy('min_days');
    }

    /**
     * Get the booking promotion records for this promotion.
     *
     * @return HasMany<BookingPromotion, $this>
     */
    public function bookingPromotions(): HasMany
    {
        return $this->hasMany(BookingPromotion::class);
    }

    /**
     * Get the vehicle volume tiers for this promotion.
     *
     * @return HasMany<PromotionVehicleTier, $this>
     */
    public function vehicleTiers(): HasMany
    {
        return $this->hasMany(PromotionVehicleTier::class)->orderBy('min_vehicles');
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include active promotions.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include promotions for a given supplier.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeForSupplier($query, ?int $supplierId)
    {
        if ($supplierId === null) {
            return $query;
        }

        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope a query to only include seasonal promotions.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeSeasonal($query)
    {
        return $query->where('type', 'seasonal');
    }

    /**
     * Scope a query to only include volume promotions.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeVolume($query)
    {
        return $query->where('type', 'volume');
    }

    /**
     * Scope a query to only include vehicle volume promotions.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeVehicleVolume($query)
    {
        return $query->where('type', 'vehicle_volume');
    }

    /**
     * Scope a query to only include promotions valid for a given date.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeValidForDate($query, string $date)
    {
        return $query->whereDate('valid_from', '<=', $date)
            ->whereDate('valid_to', '>=', $date);
    }

    /**
     * Scope a query to filter by name.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeSearch($query, string $term)
    {
        $isPostgres = $query->getConnection()->getDriverName() === 'pgsql';
        $op = $isPostgres ? 'ilike' : 'like';

        return $query->where('name', $op, "%{$term}%");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_rental_days' => 'integer',
            'free_days' => 'integer',
            'min_vehicle_count' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'applies_to_all_offices' => 'boolean',
            'applies_to_all_categories' => 'boolean',
        ];
    }
}
