<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'preferred_locale')) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE users ALTER COLUMN preferred_locale TYPE varchar(5)'),
            'mysql', 'mariadb' => DB::statement("ALTER TABLE users MODIFY preferred_locale varchar(5) NOT NULL DEFAULT 'es'"),
            default => null,
        };

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_preferred_locale_index');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'preferred_locale')) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE users ALTER COLUMN preferred_locale TYPE varchar(2)'),
            'mysql', 'mariadb' => DB::statement("ALTER TABLE users MODIFY preferred_locale varchar(2) NOT NULL DEFAULT 'es'"),
            default => null,
        };
    }
};
