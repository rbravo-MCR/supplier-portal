<?php

namespace App\Modules\Supplier\Domain\Rules;

class SupplierUserLimitRule
{
    /**
     * Determine whether additional users fit within the supplier limit.
     */
    public function allows(int $currentUsers, int $maxUsers, int $additionalUsers = 1): bool
    {
        return $currentUsers + $additionalUsers <= $maxUsers;
    }
}
