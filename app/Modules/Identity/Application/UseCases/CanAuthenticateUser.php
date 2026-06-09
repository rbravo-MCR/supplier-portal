<?php

namespace App\Modules\Identity\Application\UseCases;

use App\Models\User;

class CanAuthenticateUser
{
    /**
     * @var list<string>
     */
    private const SUPPLIER_ROLES = [
        'supplier_admin',
        'supplier_user',
    ];

    /**
     * Determine whether the user may start an authenticated session.
     */
    public function handle(User $user): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        if (! in_array($user->role, self::SUPPLIER_ROLES, true)) {
            return true;
        }

        if ($user->supplier_id === null) {
            return false;
        }

        return $user->supplier?->status === 'active';
    }
}
