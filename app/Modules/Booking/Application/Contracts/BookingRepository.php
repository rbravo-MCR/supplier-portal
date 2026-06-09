<?php

namespace App\Modules\Booking\Application\Contracts;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingRepository
{
    /**
     * List pending bookings for a supplier.
     */
    public function pendingForSupplier(int $supplierId, int $perPage): LengthAwarePaginator;

    /**
     * Find a booking by internal id.
     */
    public function find(int $bookingId): ?Booking;

    /**
     * Find a booking owned by a supplier.
     */
    public function findForSupplier(int $bookingId, int $supplierId): ?Booking;

    /**
     * Persist the booking status.
     */
    public function updateStatus(Booking $booking, string $status): Booking;
}
