<?php

namespace App\Modules\Booking\Application\DTOs;

class ConfirmBookingData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly int $bookingId,
        public readonly int $userId,
    ) {}
}
