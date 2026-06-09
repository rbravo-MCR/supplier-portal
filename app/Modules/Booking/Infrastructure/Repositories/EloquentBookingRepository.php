<?php

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Models\Booking;
use App\Modules\Booking\Application\Contracts\BookingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentBookingRepository implements BookingRepository
{
    /**
     * List pending bookings for a supplier.
     */
    public function pendingForSupplier(int $supplierId, int $perPage): LengthAwarePaginator
    {
        return Booking::query()
            ->with('supplier')
            ->where('supplier_id', $supplierId)
            ->where('status', 'pending')
            ->orderBy('pickup_at')
            ->paginate($perPage);
    }

    /**
     * Find a booking by internal id.
     */
    public function find(int $bookingId): ?Booking
    {
        return Booking::query()
            ->with('supplier')
            ->whereKey($bookingId)
            ->first();
    }

    /**
     * Find a booking owned by a supplier.
     */
    public function findForSupplier(int $bookingId, int $supplierId): ?Booking
    {
        return Booking::query()
            ->where('supplier_id', $supplierId)
            ->whereKey($bookingId)
            ->first();
    }

    /**
     * Persist the booking status.
     */
    public function updateStatus(Booking $booking, string $status): Booking
    {
        $booking->forceFill(['status' => $status])->save();

        return $booking->refresh();
    }
}
