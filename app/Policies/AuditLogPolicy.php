<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
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
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::PLATFORM_ROLES, true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
