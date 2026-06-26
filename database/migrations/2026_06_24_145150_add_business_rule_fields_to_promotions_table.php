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
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('min_rental_days')->nullable()->after('discount_value');
            $table->unsignedInteger('free_days')->nullable()->after('min_rental_days');
            $table->unsignedInteger('min_vehicle_count')->default(1)->after('free_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['min_rental_days', 'free_days', 'min_vehicle_count']);
        });
    }
};
