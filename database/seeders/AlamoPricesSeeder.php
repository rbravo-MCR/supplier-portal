<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Rate;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AlamoPricesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplier = Supplier::firstOrCreate(
            ['code' => 'ALAMO'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'ALAMO CAR RENTAL',
                'status' => 'active',
                'contact_name' => 'ALAMO Contact',
                'email' => 'contacto@alamo.com',
                'phone' => '+52 998 000 0000',
            ]
        );

        $mxn = Currency::where('code', 'MXN')->firstOrFail();

        $actor = User::first() ?? User::factory()->create([
            'name' => 'Seeder Admin',
            'email' => 'admin@supplier-portal.test',
        ]);

        $rates = [
            ['office_code' => 'CUN', 'vehicle_class' => 'ECONOMY',      'acriss_code' => 'ECAR', 'rate_plan_code' => 'STD',     'base_price' => 620.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'CUN', 'vehicle_class' => 'COMPACT',      'acriss_code' => 'CDAR', 'rate_plan_code' => 'STD',     'base_price' => 850.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'CUN', 'vehicle_class' => 'COMPACT',      'acriss_code' => 'CDAR', 'rate_plan_code' => 'WEEKEND', 'base_price' => 890.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'CUN', 'vehicle_class' => 'INTERMEDIATE', 'acriss_code' => 'IDAR', 'rate_plan_code' => 'STD',     'base_price' => 1050.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'CUN', 'vehicle_class' => 'SUV',          'acriss_code' => 'IFAR', 'rate_plan_code' => 'STD',     'base_price' => 1250.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'CUN', 'vehicle_class' => 'SUV',          'acriss_code' => 'IFAR', 'rate_plan_code' => 'WEEKLY',  'base_price' => 6800.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'MEX', 'vehicle_class' => 'ECONOMY',      'acriss_code' => 'ECAR', 'rate_plan_code' => 'STD',     'base_price' => 580.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'MEX', 'vehicle_class' => 'COMPACT',      'acriss_code' => 'CDAR', 'rate_plan_code' => 'STD',     'base_price' => 780.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'MEX', 'vehicle_class' => 'STANDARD',     'acriss_code' => 'SDAR', 'rate_plan_code' => 'STD',     'base_price' => 1100.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
            ['office_code' => 'GDL', 'vehicle_class' => 'MINIVAN',      'acriss_code' => 'MVAR', 'rate_plan_code' => 'STD',     'base_price' => 1800.00, 'valid_from' => '2026-07-01', 'valid_to' => '2026-07-31'],
        ];

        foreach ($rates as $data) {
            Rate::firstOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'office_code' => $data['office_code'],
                    'acriss_code' => $data['acriss_code'],
                    'rate_plan_code' => $data['rate_plan_code'],
                    'valid_from' => $data['valid_from'],
                    'valid_to' => $data['valid_to'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'vehicle_class' => $data['vehicle_class'],
                    'currency_id' => $mxn->id,
                    'base_price' => $data['base_price'],
                    'status' => 'active',
                    'version' => 1,
                    'created_by' => $actor->id,
                ]
            );
        }
    }
}
