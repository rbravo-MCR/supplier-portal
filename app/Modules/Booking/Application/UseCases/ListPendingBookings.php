<?php

namespace App\Modules\Booking\Application\UseCases;

use App\Modules\Booking\Application\Contracts\BookingRepository;
use App\Shared\Support\SupplierContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPendingBookings
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly SupplierContext $supplierContext,
    ) {}

    /**
     * List pending bookings for the authenticated supplier.
     */
    public function handle(int $perPage = 15): LengthAwarePaginator
    {
        return $this->bookings->pendingForSupplier($this->supplierContext->id(), $perPage);
    }
}
