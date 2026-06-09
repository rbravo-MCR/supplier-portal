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
        $driver = DB::getDriverName();

        Schema::table('countries', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->index('name', 'countries_name_fulltext');
            } else {
                $table->fullText('name', 'countries_name_fulltext');
            }
        });

        Schema::table('cities', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->index('name', 'cities_name_fulltext');
            } else {
                $table->fullText('name', 'cities_name_fulltext');
            }
        });

        Schema::table('zones', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->index('name', 'zones_name_fulltext');
            } else {
                $table->fullText('name', 'zones_name_fulltext');
            }
        });

        Schema::table('offices', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->index('name', 'offices_name_fulltext');
            } else {
                $table->fullText('name', 'offices_name_fulltext');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('countries', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropIndex('countries_name_fulltext');
            } else {
                $table->dropFullText('countries_name_fulltext');
            }
        });

        Schema::table('cities', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropIndex('cities_name_fulltext');
            } else {
                $table->dropFullText('cities_name_fulltext');
            }
        });

        Schema::table('zones', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropIndex('zones_name_fulltext');
            } else {
                $table->dropFullText('zones_name_fulltext');
            }
        });

        Schema::table('offices', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropIndex('offices_name_fulltext');
            } else {
                $table->dropFullText('offices_name_fulltext');
            }
        });
    }
};
