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
        Schema::create('vehicle_category_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_es');
            $table->string('name_en')->nullable();
            $table->string('vehicle_body_type');
            $table->unsignedSmallInteger('passenger_capacity_min')->nullable();
            $table->unsignedSmallInteger('passenger_capacity_max')->nullable();
            $table->string('category_family')->nullable()->index();
            $table->string('transmission_type')->nullable()->index();
            $table->string('fuel_type')->nullable()->index();
            $table->string('variant_key')->nullable()->unique();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_category_catalogs');
    }
};
