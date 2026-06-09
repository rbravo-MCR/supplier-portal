<?php

namespace App\Policies;

use App\Models\Rate;
use App\Models\User;

class RatePolicy
{
    /**
     * @var list<string>
     */
    private const PLATFORM_ROLES = [
        'super_admin',
        'admin',
        'auditor',
    ];

    /**
     * @var list<string>
     */
    private const SUPPLIER_WRITE_ROLES = [
        'supplier_admin',
    ];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [...self::PLATFORM_ROLES, ...self::SUPPLIER_WRITE_ROLES, 'supplier_user'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Rate $rate): bool
    {
        if (in_array($user->role, self::PLATFORM_ROLES, true)) {
            return true;
        }

        return $user->supplier_id !== null && $user->supplier_id === $rate->supplier_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin', ...self::SUPPLIER_WRITE_ROLES], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Rate $rate): bool
    {
        return $this->view($user, $rate)
            && in_array($user->role, ['super_admin', 'admin', ...self::SUPPLIER_WRITE_ROLES], true);
    }

    /**
     * Determine whether the user can import rates from Excel.
     */
    public function importExcel(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin', ...self::SUPPLIER_WRITE_ROLES], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Rate $rate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Rate $rate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Rate $rate): bool
    {
        return false;
    }
}
