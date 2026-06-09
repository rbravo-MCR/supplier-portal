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
        Schema::table('countries', function (Blueprint $table): void {
            $table->index(['status', 'name'], 'countries_status_name_index');
        });

        Schema::table('cities', function (Blueprint $table): void {
            $table->index(['country_id', 'status', 'name'], 'cities_country_status_name_index');
        });

        Schema::table('zones', function (Blueprint $table): void {
            $table->index(['city_id', 'status', 'name'], 'zones_city_status_name_index');
        });

        Schema::table('offices', function (Blueprint $table): void {
            $table->index(['supplier_id', 'name'], 'offices_supplier_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table): void {
            $table->dropIndex('offices_supplier_name_index');
        });

        Schema::table('zones', function (Blueprint $table): void {
            $table->dropIndex('zones_city_status_name_index');
        });

        Schema::table('cities', function (Blueprint $table): void {
            $table->dropIndex('cities_country_status_name_index');
        });

        Schema::table('countries', function (Blueprint $table): void {
            $table->dropIndex('countries_status_name_index');
        });
    }
};
