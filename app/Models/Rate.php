<?php

namespace App\Models;

use Database\Factories\RateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'supplier_id', 'office_code', 'vehicle_class', 'acriss_code', 'rate_plan_code', 'currency_id', 'base_price', 'valid_from', 'valid_to', 'min_days', 'max_days', 'status', 'version', 'operation_uuid', 'created_by'])]
class Rate extends Model
{
    /** @use HasFactory<RateFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Rate $rate) {
            if (empty($rate->uuid)) {
                $rate->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (Rate $rate): void {
            Cache::forget("rate.overlap.{$rate->supplier_id}.{$rate->office_code}.{$rate->acriss_code}.{$rate->rate_plan_code}.{$rate->valid_from}.{$rate->valid_to}");
            Cache::forget("rate.version.{$rate->supplier_id}.{$rate->office_code}.{$rate->acriss_code}.{$rate->rate_plan_code}");
        });

        static::deleted(function (Rate $rate): void {
            Cache::forget("rate.overlap.{$rate->supplier_id}.{$rate->office_code}.{$rate->acriss_code}.{$rate->rate_plan_code}.{$rate->valid_from}.{$rate->valid_to}");
            Cache::forget("rate.version.{$rate->supplier_id}.{$rate->office_code}.{$rate->acriss_code}.{$rate->rate_plan_code}");
        });

        // Note: bulk updates via Rate::query()->update() do NOT trigger model
        // events. After bulk updates, call Rate::clearRateCacheForKey(...) or
        // flush the cache tag manually.
    }

    /**
     * Get the supplier that owns the rate.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the actor that created the rate.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the currency used by this rate.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include active rates.
     *
     * @param  Builder<Rate>  $query
     * @return Builder<Rate>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include rates for a given supplier.
     *
     * @param  Builder<Rate>  $query
     * @return Builder<Rate>
     */
    public function scopeForSupplier($query, ?int $supplierId)
    {
        if ($supplierId === null) {
            return $query;
        }

        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope a query to filter by vehicle class, acriss code, office code or rate plan code.
     *
     * @param  Builder<Rate>  $query
     * @return Builder<Rate>
     */
    public function scopeSearch($query, string $term)
    {
        $isPostgres = $query->getConnection()->getDriverName() === 'pgsql';
        $op = $isPostgres ? 'ilike' : 'like';

        return $query->where(function ($query) use ($term, $op) {
            $query->where('vehicle_class', $op, "%{$term}%")
                ->orWhere('acriss_code', $op, "%{$term}%")
                ->orWhere('office_code', $op, "%{$term}%")
                ->orWhere('rate_plan_code', $op, "%{$term}%");
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
            'base_price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'min_days' => 'integer',
            'max_days' => 'integer',
            'version' => 'integer',
        ];
    }
}
