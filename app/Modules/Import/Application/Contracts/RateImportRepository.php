<?php

namespace App\Modules\Import\Application\Contracts;

use App\Models\RateImport;
use App\Modules\Import\Application\DTOs\CreateRateImportData;

interface RateImportRepository
{
    /**
     * Create import header and staging rows.
     */
    public function createWithRows(int $supplierId, CreateRateImportData $data): RateImport;
}
