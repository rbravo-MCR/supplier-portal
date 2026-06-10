<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        DB::table('currencies')->upsert(
            collect($this->currencies())
                ->map(fn (array $currency): array => [
                    ...$currency,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all(),
            ['code'],
            ['numeric_code', 'name', 'symbol', 'decimal_places', 'is_active', 'updated_at'],
        );
    }

    /**
     * @return array<int, array{code: string, numeric_code: string, name: string, symbol: string, decimal_places: int}>
     */
    private function currencies(): array
    {
        return [
            ['code' => 'USD', 'numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'numeric_code' => '978', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
            ['code' => 'MXN', 'numeric_code' => '484', 'name' => 'Mexican Peso', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'CAD', 'numeric_code' => '124', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'decimal_places' => 2],
            ['code' => 'GBP', 'numeric_code' => '826', 'name' => 'Pound Sterling', 'symbol' => '£', 'decimal_places' => 2],
            ['code' => 'BRL', 'numeric_code' => '986', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'decimal_places' => 2],
            ['code' => 'ARS', 'numeric_code' => '032', 'name' => 'Argentine Peso', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'COP', 'numeric_code' => '170', 'name' => 'Colombian Peso', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'CLP', 'numeric_code' => '152', 'name' => 'Chilean Peso', 'symbol' => '$', 'decimal_places' => 0],
            ['code' => 'PEN', 'numeric_code' => '604', 'name' => 'Sol', 'symbol' => 'S/', 'decimal_places' => 2],
            ['code' => 'UYU', 'numeric_code' => '858', 'name' => 'Peso Uruguayo', 'symbol' => '$U', 'decimal_places' => 2],
            ['code' => 'DOP', 'numeric_code' => '214', 'name' => 'Dominican Peso', 'symbol' => 'RD$', 'decimal_places' => 2],
            ['code' => 'GTQ', 'numeric_code' => '320', 'name' => 'Quetzal', 'symbol' => 'Q', 'decimal_places' => 2],
            ['code' => 'CRC', 'numeric_code' => '188', 'name' => 'Costa Rican Colon', 'symbol' => '₡', 'decimal_places' => 2],
            ['code' => 'PAB', 'numeric_code' => '590', 'name' => 'Balboa', 'symbol' => 'B/.', 'decimal_places' => 2],
            ['code' => 'JPY', 'numeric_code' => '392', 'name' => 'Yen', 'symbol' => '¥', 'decimal_places' => 0],
            ['code' => 'CNY', 'numeric_code' => '156', 'name' => 'Yuan Renminbi', 'symbol' => '¥', 'decimal_places' => 2],
            ['code' => 'KRW', 'numeric_code' => '410', 'name' => 'Won', 'symbol' => '₩', 'decimal_places' => 0],
            ['code' => 'CHF', 'numeric_code' => '756', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'decimal_places' => 2],
            ['code' => 'AUD', 'numeric_code' => '036', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'decimal_places' => 2],
            ['code' => 'NZD', 'numeric_code' => '554', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'decimal_places' => 2],
            ['code' => 'SGD', 'numeric_code' => '702', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2],
            ['code' => 'HKD', 'numeric_code' => '344', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'decimal_places' => 2],
            ['code' => 'AED', 'numeric_code' => '784', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'decimal_places' => 2],
            ['code' => 'SAR', 'numeric_code' => '682', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'decimal_places' => 2],
            ['code' => 'QAR', 'numeric_code' => '634', 'name' => 'Qatari Rial', 'symbol' => '﷼', 'decimal_places' => 2],
            ['code' => 'INR', 'numeric_code' => '356', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2],
            ['code' => 'ZAR', 'numeric_code' => '710', 'name' => 'Rand', 'symbol' => 'R', 'decimal_places' => 2],
        ];
    }
}
