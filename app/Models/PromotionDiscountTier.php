<?php

namespace App\Models;

use Database\Factories\PromotionDiscountTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['promotion_id', 'min_days', 'max_days', 'discount_value'])]
class PromotionDiscountTier extends Model
{
    /** @use HasFactory<PromotionDiscountTierFactory> */
    use HasFactory;

    /**
     * Get the promotion that owns this tier.
     *
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_days' => 'integer',
            'max_days' => 'integer',
            'discount_value' => 'decimal:2',
        ];
    }
}
