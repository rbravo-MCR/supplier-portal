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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('scope')->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['code' => 'super_admin', 'name' => 'Super administrador', 'scope' => 'platform', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'admin', 'name' => 'Administrador plataforma', 'scope' => 'platform', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'auditor', 'name' => 'Auditor', 'scope' => 'platform', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'supplier_admin', 'name' => 'Administrador proveedor', 'scope' => 'supplier', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'supplier_reservations', 'name' => 'Reservas', 'scope' => 'supplier', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'supplier_pricing', 'name' => 'Precios', 'scope' => 'supplier', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'supplier_user', 'name' => 'Usuario proveedor', 'scope' => 'supplier', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
