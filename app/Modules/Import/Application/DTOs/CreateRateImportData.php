<?php

namespace App\Modules\Import\Application\DTOs;

class CreateRateImportData
{
    /**
     * Create a new class instance.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly string $originalFilename,
        public readonly string $storedPath,
        public readonly int $uploadedBy,
        public readonly array $rows,
    ) {}
}
