<?php

namespace App\Modules\Supplier\Infrastructure\Repositories;

use App\Models\User;
use App\Modules\Supplier\Application\Contracts\SupplierUserRepository;
use Illuminate\Support\Facades\Cache;

class EloquentSupplierUserRepository implements SupplierUserRepository
{
    /**
     * @var list<string>
     */
    private const BILLABLE_STATUSES = [
        'active',
        'pending_activation',
        'pending_2fa_setup',
    ];

    private const CACHE_TTL_SECONDS = 300;

    /**
     * Count users that consume a supplier seat.
     */
    public function countBillableUsersForSupplier(int $supplierId): int
    {
        return Cache::remember(
            "supplier.{$supplierId}.billable_users.count",
            self::CACHE_TTL_SECONDS,
            function () use ($supplierId): int {
                return User::query()
                    ->where('supplier_id', $supplierId)
                    ->whereIn('status', self::BILLABLE_STATUSES)
                    ->count();
            }
        );
    }
}
