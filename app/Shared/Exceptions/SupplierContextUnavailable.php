<?php

namespace App\Shared\Exceptions;

use RuntimeException;

class SupplierContextUnavailable extends RuntimeException
{
    public static function forCurrentUser(): self
    {
        return new self('No supplier is available for the current user.');
    }
}
