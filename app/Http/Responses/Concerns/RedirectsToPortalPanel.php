<?php

namespace App\Http\Responses\Concerns;

use App\Models\User;

trait RedirectsToPortalPanel
{
    /**
     * Resolve the portal panel path for the authenticated user.
     */
    protected function redirectPathForPortalPanel($request): string
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return '/';
        }

        if (in_array($user->role, ['supplier_admin', 'supplier_user'], true)) {
            return route('supplier.dashboard', absolute: false);
        }

        return route('admin.dashboard', absolute: false);
    }
}
