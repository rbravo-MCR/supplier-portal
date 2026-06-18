<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'supplier_id', 'reservation_code', 'customer_name', 'vehicle_class', 'pickup_office_code', 'dropoff_office_code', 'pickup_at', 'dropoff_at', 'total_amount', 'currency', 'status', 'metadata'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Booking $booking) {
            if (empty($booking->uuid)) {
                $booking->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the supplier that owns the booking.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get actions recorded for the booking.
     *
     * @return HasMany<BookingAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(BookingAction::class);
    }

    /**
     * Get the promotions applied to this booking.
     *
     * @return BelongsToMany<Promotion, $this>
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'booking_promotions')
            ->withPivot(['original_amount', 'discount_amount', 'final_amount', 'applied_at']);
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include bookings for a given supplier.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeForSupplier($query, ?int $supplierId)
    {
        if ($supplierId === null) {
            return $query;
        }

        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope a query to only include pending bookings.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to filter by reservation code, customer name, vehicle class or office codes.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeSearch($query, string $term)
    {
        $isPostgres = $query->getConnection()->getDriverName() === 'pgsql';
        $op = $isPostgres ? 'ilike' : 'like';

        return $query->where(function ($query) use ($term, $op) {
            $query->where('reservation_code', $op, "%{$term}%")
                ->orWhere('customer_name', $op, "%{$term}%")
                ->orWhere('vehicle_class', $op, "%{$term}%")
                ->orWhere('pickup_office_code', $op, "%{$term}%")
                ->orWhere('dropoff_office_code', $op, "%{$term}%")
                ->orWhere('status', $op, "%{$term}%");
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
            'pickup_at' => 'datetime',
            'dropoff_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
