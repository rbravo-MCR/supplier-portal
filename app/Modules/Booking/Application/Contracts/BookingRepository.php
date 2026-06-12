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
     * Find a booking owned by a supplier, acquiring a write lock for concurrent operations.
     * Must be called within an active database transaction.
     */
    public function findForSupplierForUpdate(int $bookingId, int $supplierId): ?Booking;

    /**
     * Persist the booking status.
     */
    public function updateStatus(Booking $booking, string $status): Booking;
}
