<?php

namespace App\Modules\Pricing\Domain\Exceptions;

use RuntimeException;

class InvalidRateData extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
