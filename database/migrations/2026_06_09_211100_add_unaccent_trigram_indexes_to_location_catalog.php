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

        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(value text)
            RETURNS text
            LANGUAGE sql
            IMMUTABLE
            PARALLEL SAFE
            RETURNS NULL ON NULL INPUT
            AS $$
                SELECT public.unaccent('public.unaccent', value)
            $$;
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS countries_name_unaccent_trgm_index ON countries USING gin (immutable_unaccent(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS cities_name_unaccent_trgm_index ON cities USING gin (immutable_unaccent(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS zones_name_unaccent_trgm_index ON zones USING gin (immutable_unaccent(name) gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS zones_name_unaccent_trgm_index');
        DB::statement('DROP INDEX IF EXISTS cities_name_unaccent_trgm_index');
        DB::statement('DROP INDEX IF EXISTS countries_name_unaccent_trgm_index');
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
    }
};
