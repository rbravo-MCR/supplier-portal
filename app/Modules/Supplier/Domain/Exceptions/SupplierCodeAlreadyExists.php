<?php

namespace App\Modules\Supplier\Domain\Exceptions;

use RuntimeException;

class SupplierCodeAlreadyExists extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("Supplier code [{$code}] already exists.");
    }
}
