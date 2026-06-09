<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
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
    private const SUPPLIER_ROLES = [
        'supplier_admin',
        'supplier_user',
    ];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [...self::PLATFORM_ROLES, ...self::SUPPLIER_ROLES], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        if (in_array($user->role, self::PLATFORM_ROLES, true)) {
            return true;
        }

        return in_array($user->role, self::SUPPLIER_ROLES, true)
            && $user->supplier_id !== null
            && $user->supplier_id === $booking->supplier_id;
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
    public function update(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking) && $user->role !== 'auditor';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Booking $booking): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Booking $booking): bool
    {
        return false;
    }
}
