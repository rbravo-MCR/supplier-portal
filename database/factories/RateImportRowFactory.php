<?php

namespace Database\Factories;

use App\Models\RateImport;
use App\Models\RateImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateImportRow>
 */
class RateImportRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate_import_id' => RateImport::factory(),
            'row_number' => 1,
            'raw_data' => [
                'office_code' => 'CUN',
            ],
            'normalized_data' => null,
            'errors' => null,
            'status' => 'uploaded',
        ];
    }
}
