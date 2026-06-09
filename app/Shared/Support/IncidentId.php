<?php

namespace App\Shared\Support;

use Illuminate\Support\Str;

class IncidentId
{
    /**
     * Generate a public incident tracking code.
     */
    public static function generate(): string
    {
        return 'INC-'.Str::upper(Str::random(10));
    }
}
