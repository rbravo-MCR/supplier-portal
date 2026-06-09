<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'supplier' => [
                'code' => $this->supplier?->code,
                'name' => $this->supplier?->name,
            ],
            'reservation_code' => $this->reservation_code,
            'customer_name' => $this->customer_name,
            'vehicle_class' => $this->vehicle_class,
            'pickup_office_code' => $this->pickup_office_code,
            'dropoff_office_code' => $this->dropoff_office_code,
            'pickup_at' => $this->pickup_at?->toISOString(),
            'dropoff_at' => $this->dropoff_at?->toISOString(),
            'total_amount' => (string) $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
