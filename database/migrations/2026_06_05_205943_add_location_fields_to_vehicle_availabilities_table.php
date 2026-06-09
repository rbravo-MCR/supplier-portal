<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('vehicle_availabilities', 'office_id')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->foreignId('office_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('vehicle_availabilities', 'location_type')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->string('location_type')->nullable()->after('office_id');
            });
        }

        if (! Schema::hasColumn('vehicle_availabilities', 'location_code')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->string('location_code')->nullable()->after('location_type');
            });
        }

        if (! Schema::hasColumn('vehicle_availabilities', 'iata_code')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->string('iata_code', 3)->nullable()->after('office_code');
            });
        }

        Schema::table('vehicle_availabilities', function (Blueprint $table) {
            $table->string('office_code')->nullable()->change();
        });

        DB::table('vehicle_availabilities')->update([
            'location_type' => DB::raw("case when office_code is null and iata_code is not null then 'iata' else coalesce(location_type, 'office') end"),
            'location_code' => DB::raw("coalesce(location_code, office_code, iata_code, 'UNKNOWN')"),
        ]);

        if (Schema::hasIndex('vehicle_availabilities', 'vehicle_availability_unique_window')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->dropUnique('vehicle_availability_unique_window');
            });
        }

        if (Schema::hasIndex('vehicle_availabilities', 'vehicle_availabilities_supplier_id_location_type_location_code_')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->dropIndex('vehicle_availabilities_supplier_id_location_type_location_code_');
            });
        }

        Schema::table('vehicle_availabilities', function (Blueprint $table) {
            $table->unique([
                'supplier_id',
                'location_type',
                'location_code',
                'acriss_code',
                'valid_from',
                'valid_to',
            ], 'vehicle_availability_location_window');
            $table->index(['supplier_id', 'location_type', 'location_code'], 'vehicle_availability_location_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('vehicle_availabilities', 'vehicle_availability_location_window')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->dropUnique('vehicle_availability_location_window');
            });
        }

        if (Schema::hasIndex('vehicle_availabilities', 'vehicle_availability_location_index')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->dropIndex('vehicle_availability_location_index');
            });
        }

        Schema::table('vehicle_availabilities', function (Blueprint $table) {
            $table->unique([
                'supplier_id',
                'office_code',
                'acriss_code',
                'valid_from',
                'valid_to',
            ], 'vehicle_availability_unique_window');
            $table->string('office_code')->nullable(false)->change();
        });

        if (Schema::hasColumn('vehicle_availabilities', 'office_id')) {
            Schema::table('vehicle_availabilities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('office_id');
            });
        }

        Schema::table('vehicle_availabilities', function (Blueprint $table) {
            $table->dropColumn(['location_type', 'location_code', 'iata_code']);
        });
    }
};
