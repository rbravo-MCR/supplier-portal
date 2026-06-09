<?php

namespace App\Modules\Supplier\Application\UseCases;

use App\Models\Supplier;
use App\Modules\Supplier\Application\Contracts\SupplierUserRepository;
use App\Modules\Supplier\Domain\Rules\SupplierUserLimitRule;

class CanCreateSupplierUser
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private readonly SupplierUserRepository $users,
        private readonly SupplierUserLimitRule $limitRule,
    ) {}

    /**
     * Determine whether a supplier may create additional users.
     */
    public function handle(Supplier $supplier, int $additionalUsers = 1): bool
    {
        $currentUsers = $this->users->countBillableUsersForSupplier($supplier->id);
        $maxUsers = $supplier->max_users ?? (int) config('supplier.max_users');

        return $this->limitRule->allows($currentUsers, $maxUsers, $additionalUsers);
    }
}
