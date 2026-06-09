<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX IF NOT EXISTS countries_name_trgm_idx ON countries USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS cities_name_trgm_idx ON cities USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS zones_name_trgm_idx ON zones USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS offices_name_trgm_idx ON offices USING GIN (name gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS offices_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS zones_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS cities_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS countries_name_trgm_idx');
    }
};
