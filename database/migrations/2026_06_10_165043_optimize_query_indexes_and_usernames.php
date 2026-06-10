<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->normalizeUsernames();
        $this->replaceUsernameIndexes();
        $this->createQueryIndexes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropQueryIndexes();
        $this->restoreUsernameIndexes();
    }

    private function normalizeUsernames(): void
    {
        DB::table('users')
            ->select(['id', 'email', 'username'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $baseUsername = Str::of((string) ($user->username ?: Str::before($user->email, '@')))
                        ->lower()
                        ->replaceMatches('/[^a-z0-9._-]+/', '-')
                        ->trim('-._')
                        ->value() ?: "user-{$user->id}";

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['username' => $baseUsername]);
                }
            }, 'id');

        DB::table('users')
            ->select(['id', 'username'])
            ->whereNotNull('username')
            ->orderBy('username')
            ->orderBy('id')
            ->get()
            ->groupBy('username')
            ->filter(fn ($users): bool => $users->count() > 1)
            ->each(function ($users): void {
                $users->skip(1)->each(function ($user): void {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['username' => "{$user->username}-{$user->id}"]);
                });
            });
    }

    private function replaceUsernameIndexes(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_internal_username_unique');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', 'users_supplier_username_unique')) {
                $table->dropUnique('users_supplier_username_unique');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username', 'users_username_unique');
        });
    }

    private function restoreUsernameIndexes(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasIndex('users', 'users_username_unique')) {
                $table->dropUnique('users_username_unique');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['supplier_id', 'username'], 'users_supplier_username_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_internal_username_unique ON users (username) WHERE supplier_id IS NULL');
        }
    }

    private function createQueryIndexes(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS bookings_supplier_status_pickup_idx ON bookings (supplier_id, status, pickup_at, id)');
            DB::statement('CREATE INDEX IF NOT EXISTS rates_supplier_status_valid_from_id_idx ON rates (supplier_id, status, valid_from DESC, id DESC)');
            DB::statement('CREATE INDEX IF NOT EXISTS rates_version_lookup_idx ON rates (supplier_id, office_code, acriss_code, rate_plan_code, version DESC)');
            DB::statement('CREATE INDEX IF NOT EXISTS offices_supplier_zone_name_idx ON offices (supplier_id, zone_id, name)');

            return;
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['supplier_id', 'status', 'pickup_at', 'id'], 'bookings_supplier_status_pickup_idx');
        });

        Schema::table('rates', function (Blueprint $table): void {
            $table->index(['supplier_id', 'status', 'valid_from', 'id'], 'rates_supplier_status_valid_from_id_idx');
            $table->index(['supplier_id', 'office_code', 'acriss_code', 'rate_plan_code', 'version'], 'rates_version_lookup_idx');
        });

        Schema::table('offices', function (Blueprint $table): void {
            $table->index(['supplier_id', 'zone_id', 'name'], 'offices_supplier_zone_name_idx');
        });
    }

    private function dropQueryIndexes(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS offices_supplier_zone_name_idx');
            DB::statement('DROP INDEX IF EXISTS rates_version_lookup_idx');
            DB::statement('DROP INDEX IF EXISTS rates_supplier_status_valid_from_id_idx');
            DB::statement('DROP INDEX IF EXISTS bookings_supplier_status_pickup_idx');

            return;
        }

        Schema::table('offices', function (Blueprint $table): void {
            $table->dropIndex('offices_supplier_zone_name_idx');
        });

        Schema::table('rates', function (Blueprint $table): void {
            $table->dropIndex('rates_version_lookup_idx');
            $table->dropIndex('rates_supplier_status_valid_from_id_idx');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_supplier_status_pickup_idx');
        });
    }
};
