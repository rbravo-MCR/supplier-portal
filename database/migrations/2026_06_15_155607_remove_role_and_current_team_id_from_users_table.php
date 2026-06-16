<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_current_team_id_foreign');
            $table->dropColumn('current_team_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('current_team_id')->nullable()->after('supplier_id');
            $table->foreign('current_team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }
};
