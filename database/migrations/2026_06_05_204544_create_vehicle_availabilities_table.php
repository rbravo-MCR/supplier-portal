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
        Schema::create('vehicle_availabilities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('office_code');
            $table->string('vehicle_class');
            $table->string('acriss_code', 4);
            $table->unsignedInteger('available_quantity');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('status')->default('available');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique([
                'supplier_id',
                'office_code',
                'acriss_code',
                'valid_from',
                'valid_to',
            ], 'vehicle_availability_unique_window');
            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'office_code', 'valid_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_availabilities');
    }
};
