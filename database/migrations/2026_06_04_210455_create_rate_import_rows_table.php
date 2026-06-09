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
        Schema::create('rate_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status')->index();
            $table->timestamps();

            $table->index('rate_import_id');
            $table->index(['rate_import_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_import_rows');
    }
};
