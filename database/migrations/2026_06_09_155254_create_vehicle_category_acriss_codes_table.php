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
        Schema::create('vehicle_category_acriss_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_category_catalog_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('code', 4)->index();
            $table->timestamps();

            $table->unique(['vehicle_category_catalog_id', 'code'], 'vehicle_category_acriss_catalog_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_category_acriss_codes');
    }
};
