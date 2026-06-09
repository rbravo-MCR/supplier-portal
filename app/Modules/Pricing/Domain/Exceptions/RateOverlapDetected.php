<?php

namespace App\Modules\Pricing\Domain\Exceptions;

use RuntimeException;

class RateOverlapDetected extends RuntimeException
{
    public static function forRateKey(): self
    {
        return new self('Rate validity overlaps an existing active rate.');
    }
}
