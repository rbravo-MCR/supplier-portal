<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasIndex('cities', 'cities_country_id_name_unique')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropUnique('cities_country_id_name_unique');
            });
        }

        if (! Schema::hasIndex('cities', 'cities_country_name_index')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->index(['country_id', 'name'], 'cities_country_name_index');
            });
        }

        if (Schema::hasIndex('zones', 'zones_city_id_name_unique')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->dropUnique('zones_city_id_name_unique');
            });
        }

        if (! Schema::hasIndex('zones', 'zones_city_name_index')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->index(['city_id', 'name'], 'zones_city_name_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('cities', 'cities_country_name_index')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropIndex('cities_country_name_index');
            });
        }

        if (! Schema::hasIndex('cities', 'cities_country_id_name_unique')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->unique(['country_id', 'name']);
            });
        }

        if (Schema::hasIndex('zones', 'zones_city_name_index')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->dropIndex('zones_city_name_index');
            });
        }

        if (! Schema::hasIndex('zones', 'zones_city_id_name_unique')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->unique(['city_id', 'name']);
            });
        }
    }
};
