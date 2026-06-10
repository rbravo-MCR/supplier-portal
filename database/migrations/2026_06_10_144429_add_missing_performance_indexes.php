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
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->index(['supplier_id', 'vehicle_category_catalog_id'], 'vehicle_categories_supplier_catalog_idx');
        });

        Schema::table('rate_imports', function (Blueprint $table) {
            $table->index(['supplier_id', 'created_at'], 'rate_imports_supplier_created_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['supplier_id', 'created_at'], 'audit_logs_supplier_created_idx');
        });

        Schema::table('vehicle_availabilities', function (Blueprint $table) {
            $table->index(['supplier_id', 'location_code', 'valid_from', 'valid_to'], 'vehicle_availabilities_supplier_location_dates_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropIndex('vehicle_categories_supplier_catalog_idx');
        });

        Schema::table('rate_imports', function (Blueprint $table) {
            $table->dropIndex('rate_imports_supplier_created_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_supplier_created_idx');
        });

        Schema::table('vehicle_availabilities', function (Blueprint $table) {
            $table->dropIndex('vehicle_availabilities_supplier_location_dates_idx');
        });
    }
};
