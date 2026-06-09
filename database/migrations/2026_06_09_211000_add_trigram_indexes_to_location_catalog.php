<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS countries_name_trgm_index ON countries USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS cities_name_trgm_index ON cities USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS zones_name_trgm_index ON zones USING gin (name gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS zones_name_trgm_index');
        DB::statement('DROP INDEX IF EXISTS cities_name_trgm_index');
        DB::statement('DROP INDEX IF EXISTS countries_name_trgm_index');
    }
};
