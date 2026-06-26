<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['promotion_id', 'min_vehicles', 'max_vehicles', 'discount_value'])]
class PromotionVehicleTier extends Model
{
    /**
     * Get the promotion that owns the tier.
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
            'min_vehicles' => 'integer',
            'max_vehicles' => 'integer',
            'discount_value' => 'decimal:2',
        ];
    }
}
