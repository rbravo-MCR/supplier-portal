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
        Schema::create('system_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_id')->unique();
            $table->string('correlation_id')->nullable()->index();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module')->index();
            $table->string('action');
            $table->string('severity')->index();
            $table->string('exception_class')->nullable();
            $table->text('safe_message');
            $table->text('technical_message')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('responsible')->nullable();
            $table->text('executive_summary')->nullable();
            $table->unsignedInteger('affected_users_count')->nullable();
            $table->json('affected_suppliers')->nullable();
            $table->json('affected_functionality')->nullable();
            $table->json('timeline')->nullable();
            $table->text('technical_root_cause')->nullable();
            $table->text('organizational_root_cause')->nullable();
            $table->json('contributing_factors')->nullable();
            $table->json('immediate_corrective_actions')->nullable();
            $table->json('permanent_corrective_actions')->nullable();
            $table->json('future_prevention')->nullable();
            $table->string('architect_approved_by')->nullable();
            $table->string('technical_lead_approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_incidents');
    }
};
