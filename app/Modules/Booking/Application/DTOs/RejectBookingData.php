<?php

namespace App\Modules\Booking\Application\DTOs;

class RejectBookingData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly int $bookingId,
        public readonly int $userId,
        public readonly string $reason,
    ) {}
}
