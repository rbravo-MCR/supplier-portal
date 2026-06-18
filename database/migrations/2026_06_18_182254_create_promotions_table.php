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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // seasonal | volume
            $table->string('discount_type'); // percentage | fixed_amount
            $table->decimal('discount_value', 12, 2);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->string('status')->index();
            $table->boolean('applies_to_all_offices')->default(true);
            $table->boolean('applies_to_all_categories')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'valid_from', 'valid_to']);
            $table->index(['supplier_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
