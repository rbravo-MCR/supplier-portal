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
            $table->foreignId('vehicle_category_catalog_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('supplier_code')->nullable()->after('vehicle_category_catalog_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropForeign(['vehicle_category_catalog_id']);
            $table->dropColumn(['vehicle_category_catalog_id', 'supplier_code']);
        });
    }
};
