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
        $this->backfillRoleIds();
        $this->normalizeEmails();
        $this->normalizeUsernames();

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->change();
            }

            if (Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable(false)->change();
            }

            if (Schema::hasColumn('users', 'role')) {
                if (Schema::hasIndex('users', 'users_role_index')) {
                    $table->dropIndex('users_role_index');
                }

                $table->dropColumn('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->nullable()->index();
            }
        });

        DB::table('users')
            ->select(['id', 'role_id'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                $roleCodes = DB::table('roles')
                    ->whereIn('id', $users->pluck('role_id')->filter()->all())
                    ->pluck('code', 'id');

                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'role' => $roleCodes[$user->role_id] ?? 'supplier_user',
                        ]);
                }
            }, 'id');

        DB::table('users')
            ->whereNull('email')
            ->update(['email' => DB::raw($this->generatedEmailExpression())]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->string('username')->nullable()->change();
        });
    }

    private function backfillRoleIds(): void
    {
        if (! Schema::hasColumn('users', 'role_id') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->whereNull('role_id')
            ->whereNotNull('role')
            ->update([
                'role_id' => DB::raw('(select roles.id from roles where roles.code = users.role limit 1)'),
            ]);

        $fallbackRoleId = DB::table('roles')
            ->where('code', 'supplier_user')
            ->value('id');

        if ($fallbackRoleId !== null) {
            DB::table('users')
                ->whereNull('role_id')
                ->update(['role_id' => $fallbackRoleId]);
        }
    }

    private function normalizeEmails(): void
    {
        DB::table('users')
            ->where('email', '')
            ->update(['email' => null]);
    }

    private function normalizeUsernames(): void
    {
        DB::table('users')
            ->select(['id', 'name', 'email', 'username'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $username = Str::of((string) ($user->username ?: $user->email ?: $user->name))
                        ->before('@')
                        ->lower()
                        ->replaceMatches('/[^a-z0-9._-]+/', '-')
                        ->trim('-._')
                        ->value() ?: "user-{$user->id}";

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['username' => $username]);
                }
            }, 'id');

        DB::table('users')
            ->select(['id', 'username'])
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

    private function generatedEmailExpression(): string
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            return "'user-' || id || '@example.invalid'";
        }

        return "concat('user-', id, '@example.invalid')";
    }
};
