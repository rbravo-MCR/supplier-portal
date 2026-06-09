<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleAvailabilityResource extends JsonResource
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
            'location_type' => $this->location_type,
            'location_code' => $this->location_code,
            'office' => $this->whenLoaded('office', fn () => [
                'uuid' => $this->office?->uuid,
                'name' => $this->office?->name,
                'code' => $this->office?->code,
                'iata_code' => $this->office?->iata_code,
            ]),
            'office_code' => $this->office_code,
            'iata_code' => $this->iata_code,
            'vehicle_class' => $this->vehicle_class,
            'acriss_code' => $this->acriss_code,
            'available_quantity' => $this->available_quantity,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
