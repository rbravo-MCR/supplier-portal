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
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('office_code');
            $table->string('vehicle_class');
            $table->string('acriss_code');
            $table->string('rate_plan_code');
            $table->string('currency', 3);
            $table->decimal('base_price', 12, 2);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->unsignedInteger('min_days')->nullable();
            $table->unsignedInteger('max_days')->nullable();
            $table->string('status')->index();
            $table->unsignedInteger('version');
            $table->uuid('operation_uuid')->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'office_code', 'acriss_code', 'rate_plan_code'], 'rates_supplier_rate_key_index');
            $table->index(['supplier_id', 'valid_from', 'valid_to']);
            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rates');
    }
};
