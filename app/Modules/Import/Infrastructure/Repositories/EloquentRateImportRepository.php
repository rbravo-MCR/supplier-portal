<?php

namespace App\Modules\Import\Infrastructure\Repositories;

use App\Models\RateImport;
use App\Modules\Import\Application\Contracts\RateImportRepository;
use App\Modules\Import\Application\DTOs\CreateRateImportData;
use Illuminate\Support\Facades\DB;

class EloquentRateImportRepository implements RateImportRepository
{
    /**
     * Create import header and staging rows.
     */
    public function createWithRows(int $supplierId, CreateRateImportData $data): RateImport
    {
        return DB::transaction(function () use ($data, $supplierId): RateImport {
            $rateImport = RateImport::query()->create([
                'supplier_id' => $supplierId,
                'original_filename' => $data->originalFilename,
                'stored_path' => $data->storedPath,
                'status' => 'uploaded',
                'total_rows' => count($data->rows),
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'uploaded_by' => $data->uploadedBy,
            ]);

            foreach (array_values($data->rows) as $index => $row) {
                $rateImport->rows()->create([
                    'row_number' => $index + 1,
                    'raw_data' => $row,
                    'normalized_data' => null,
                    'errors' => null,
                    'status' => 'uploaded',
                ]);
            }

            return $rateImport->refresh();
        });
    }
}
