<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'super_admin', 'name' => 'Super administrador', 'scope' => 'platform', 'status' => 'active'],
            ['code' => 'admin', 'name' => 'Administrador plataforma', 'scope' => 'platform', 'status' => 'active'],
            ['code' => 'auditor', 'name' => 'Auditor', 'scope' => 'platform', 'status' => 'active'],
            ['code' => 'supplier_admin', 'name' => 'Administrador proveedor', 'scope' => 'supplier', 'status' => 'active'],
            ['code' => 'supplier_reservations', 'name' => 'Reservas', 'scope' => 'supplier', 'status' => 'active'],
            ['code' => 'supplier_pricing', 'name' => 'Precios', 'scope' => 'supplier', 'status' => 'active'],
            ['code' => 'supplier_user', 'name' => 'Usuario proveedor', 'scope' => 'supplier', 'status' => 'active'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['code' => $role['code']], $role);
        }
    }
}
