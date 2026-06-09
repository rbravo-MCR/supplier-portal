<?php

namespace App\Modules\Import\Domain\Exceptions;

use RuntimeException;

class RateImportCannotBeEmpty extends RuntimeException
{
    public static function make(): self
    {
        return new self('Rate import must contain at least one row.');
    }
}
