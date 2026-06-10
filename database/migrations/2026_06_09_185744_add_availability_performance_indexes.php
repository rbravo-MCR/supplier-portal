<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_availabilities', function (Blueprint $table): void {
            $table->index(
                ['supplier_id', 'status', 'valid_from', 'valid_to'],
                'va_supplier_status_dates_idx'
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE INDEX va_daterange_gist ON vehicle_availabilities USING GIST (daterange(valid_from, valid_to, \'[]\'))'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS va_daterange_gist');
        }

        Schema::table('vehicle_availabilities', function (Blueprint $table): void {
            $table->dropIndex('va_supplier_status_dates_idx');
        });
    }
};
