<?php

namespace App\Models;

use Database\Factories\RateImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rate_import_id', 'row_number', 'raw_data', 'normalized_data', 'errors', 'status'])]
class RateImportRow extends Model
{
    /** @use HasFactory<RateImportRowFactory> */
    use HasFactory;

    /**
     * Get the parent import.
     *
     * @return BelongsTo<RateImport, $this>
     */
    public function rateImport(): BelongsTo
    {
        return $this->belongsTo(RateImport::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'normalized_data' => 'array',
            'errors' => 'array',
        ];
    }
}
