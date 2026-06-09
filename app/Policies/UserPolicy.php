<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * @var list<string>
     */
    private const PLATFORM_VIEW_ROLES = [
        'super_admin',
        'admin',
        'auditor',
    ];

    /**
     * @var list<string>
     */
    private const PLATFORM_WRITE_ROLES = [
        'super_admin',
        'admin',
    ];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::PLATFORM_VIEW_ROLES, true)
            || $user->role === 'supplier_admin';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if (in_array($user->role, self::PLATFORM_VIEW_ROLES, true)) {
            return true;
        }

        return $this->isSupplierAdminForOwnSupplier($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, self::PLATFORM_WRITE_ROLES, true)
            || ($user->role === 'supplier_admin' && $user->supplier_id !== null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if (in_array($user->role, self::PLATFORM_WRITE_ROLES, true)) {
            return true;
        }

        return $this->isSupplierAdminForOwnSupplier($user, $model);
    }

    /**
     * Determine whether the user can disable the model.
     */
    public function disable(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether a supplier admin is operating within their supplier.
     */
    private function isSupplierAdminForOwnSupplier(User $user, User $model): bool
    {
        return $user->role === 'supplier_admin'
            && $user->supplier_id !== null
            && $user->supplier_id === $model->supplier_id;
    }
}
