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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        DB::table('users')
            ->select(['id', 'email', 'supplier_id'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $baseUsername = Str::of((string) Str::before($user->email, '@'))
                        ->lower()
                        ->replaceMatches('/[^a-z0-9._-]+/', '-')
                        ->trim('-._')
                        ->value() ?: "user-{$user->id}";

                    $username = $baseUsername;
                    $suffix = 2;

                    while ($this->usernameExists((int) $user->id, $user->supplier_id, $username)) {
                        $username = "{$baseUsername}-{$suffix}";
                        $suffix++;
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['username' => $username]);
                }
            }, 'id');

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['supplier_id', 'username'], 'users_supplier_username_unique');
        });

        DB::statement('CREATE UNIQUE INDEX users_internal_username_unique ON users (username) WHERE supplier_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_internal_username_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_supplier_username_unique');
            $table->dropColumn('username');
        });
    }

    private function usernameExists(int $currentUserId, ?int $supplierId, string $username): bool
    {
        return DB::table('users')
            ->where('id', '!=', $currentUserId)
            ->where('username', $username)
            ->where(function ($query) use ($supplierId): void {
                if ($supplierId === null) {
                    $query->whereNull('supplier_id');

                    return;
                }

                $query->where('supplier_id', $supplierId);
            })
            ->exists();
    }
};
