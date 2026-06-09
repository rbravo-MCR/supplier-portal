<?php

namespace App\Modules\Supplier\Application\Contracts;

interface SupplierUserRepository
{
    /**
     * Count users that consume a supplier seat.
     */
    public function countBillableUsersForSupplier(int $supplierId): int;
}
