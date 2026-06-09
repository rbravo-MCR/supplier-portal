<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Scope a query to only include bookings for a given supplier.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Booking>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Booking>
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
     * @param  \Illuminate\Database\Eloquent\Builder<Booking>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Booking>
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to filter by reservation code, customer name, vehicle class or office codes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Booking>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Booking>
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('reservation_code', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('vehicle_class', 'like', "%{$term}%")
                ->orWhere('pickup_office_code', 'like', "%{$term}%")
                ->orWhere('dropoff_office_code', 'like', "%{$term}%")
                ->orWhere('status', 'like', "%{$term}%");
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
