<?php

namespace Database\Factories;

use App\Models\RateImport;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RateImport>
 */
class RateImportFactory extends Factory
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
            'original_filename' => 'rates.xlsx',
            'stored_path' => 'imports/rates.xlsx',
            'status' => 'uploaded',
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'uploaded_by' => User::factory(),
        ];
    }
}
