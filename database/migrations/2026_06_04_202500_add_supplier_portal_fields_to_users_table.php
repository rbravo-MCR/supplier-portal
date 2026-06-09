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
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('supplier_id')->nullable()->after('uuid')->constrained()->nullOnDelete();
            $table->string('role')->default('supplier_user')->after('password')->index();
            $table->string('status')->default('active')->after('role')->index();
            $table->boolean('two_factor_enabled')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');

            $table->index(['supplier_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['supplier_id', 'status']);
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn([
                'uuid',
                'role',
                'status',
                'two_factor_enabled',
                'last_login_at',
            ]);
        });
    }
};
