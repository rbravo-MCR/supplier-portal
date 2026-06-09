<?php

namespace App\Models;

use Database\Factories\RateImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'supplier_id', 'original_filename', 'stored_path', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'uploaded_by'])]
class RateImport extends Model
{
    /** @use HasFactory<RateImportFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (RateImport $rateImport) {
            if (empty($rateImport->uuid)) {
                $rateImport->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the supplier that owns the import.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Scope by supplier.
     */
    public function scopeForSupplier(Builder $query, ?int $supplierId): Builder
    {
        return $query->when($supplierId, fn (Builder $query): Builder => $query->where('supplier_id', $supplierId));
    }

    /**
     * Get the user that uploaded the import.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get rows staged for this import.
     *
     * @return HasMany<RateImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(RateImportRow::class);
    }

    /**
     * Get the route key for public URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
        ];
    }
}
