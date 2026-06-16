<?php

namespace App\Shared\Support;

use App\Models\Supplier;
use App\Models\User;
use App\Shared\Exceptions\SupplierContextUnavailable;
use Illuminate\Support\Facades\Auth;

class SupplierContext
{
    /**
     * Get the supplier assigned to the authenticated user.
     */
    public function current(): Supplier
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->supplier_id === null) {
            throw SupplierContextUnavailable::forCurrentUser();
        }

        $supplier = $user->supplier()->first();

        if (! $supplier instanceof Supplier) {
            throw SupplierContextUnavailable::forCurrentUser();
        }

        return $supplier;
    }

    /**
     * Get the current supplier internal identifier.
     */
    public function id(): int
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->supplier_id === null) {
            throw SupplierContextUnavailable::forCurrentUser();
        }

        return $user->supplier_id;
    }

    /**
     * Determine whether a user belongs to the current supplier.
     */
    public function allows(User $user): bool
    {
        return $user->supplier_id !== null && $user->supplier_id === $this->id();
    }
}
