<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Rate;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Rate>
 */
class RateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'supplier_id' => Supplier::factory(),
            'office_code' => 'CUN',
            'vehicle_class' => 'SUV',
            'acriss_code' => 'IFAR',
            'rate_plan_code' => 'STD',
            'currency_id' => fn () => Currency::query()->firstOrCreate(
                ['code' => 'USD'],
                ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
            )->id,
            'base_price' => 100,
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addDays(7)->toDateString(),
            'min_days' => null,
            'max_days' => null,
            'status' => 'active',
            'version' => 1,
            'operation_uuid' => null,
            'created_by' => User::factory(),
        ];
    }
}
