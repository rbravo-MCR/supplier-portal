<?php

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class BookingMustBePending extends RuntimeException
{
    public static function forOperation(string $operation): self
    {
        return new self("Only pending bookings can be {$operation}.");
    }
}
