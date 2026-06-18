<?php

namespace App\Modules\Promotions\Domain\Exceptions;

use RuntimeException;

class PromotionOverlapDetected extends RuntimeException
{
    public static function forSeasonal(): self
    {
        return new self('A seasonal promotion already overlaps the given date range for this supplier.');
    }

    public static function forVolume(): self
    {
        return new self('A volume promotion already overlaps the given date range for this supplier.');
    }
}
