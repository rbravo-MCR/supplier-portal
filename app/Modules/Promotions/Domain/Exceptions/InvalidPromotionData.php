<?php

namespace App\Modules\Promotions\Domain\Exceptions;

use RuntimeException;

class InvalidPromotionData extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
