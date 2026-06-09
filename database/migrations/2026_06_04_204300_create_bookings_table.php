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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('reservation_code');
            $table->string('customer_name');
            $table->string('vehicle_class')->nullable();
            $table->string('pickup_office_code');
            $table->string('dropoff_office_code');
            $table->timestamp('pickup_at');
            $table->timestamp('dropoff_at');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'reservation_code']);
            $table->index(['supplier_id', 'pickup_at']);
            $table->index(['supplier_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
