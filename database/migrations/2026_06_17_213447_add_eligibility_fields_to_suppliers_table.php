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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('country_id')
                ->nullable()
                ->after('code')
                ->constrained('countries')
                ->restrictOnDelete();

            $table->string('integration_type')->default('none')->after('country_id');

            $table->index(['country_id', 'integration_type'], 'idx_suppliers_country_integration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex('idx_suppliers_country_integration');
            $table->dropColumn(['country_id', 'integration_type']);
        });
    }
};
