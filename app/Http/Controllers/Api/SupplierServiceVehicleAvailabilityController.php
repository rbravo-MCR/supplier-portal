<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierServiceVehicleAvailabilityRequest;
use App\Http\Resources\VehicleAvailabilityResource;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\VehicleAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierServiceVehicleAvailabilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreSupplierServiceVehicleAvailabilityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $availabilities = DB::transaction(function () use ($validated) {
            $supplier = Supplier::query()
                ->where('code', $validated['supplier_code'])
                ->firstOrFail();

            return collect($validated['items'])
                ->map(function (array $item) use ($supplier): VehicleAvailability {
                    $validFrom = Carbon::parse($item['valid_from'])->startOfDay();
                    $validTo = Carbon::parse($item['valid_to'])->startOfDay();
                    $officeCode = isset($item['office_code']) ? str($item['office_code'])->upper()->toString() : null;
                    $iataCode = isset($item['iata_code']) ? str($item['iata_code'])->upper()->toString() : null;
                    $locationType = $iataCode !== null ? 'iata' : 'office';
                    $locationCode = $iataCode ?? $officeCode;
                    $office = $this->findOffice($supplier, $officeCode, $iataCode);

                    return VehicleAvailability::query()->updateOrCreate(
                        [
                            'supplier_id' => $supplier->id,
                            'location_type' => $locationType,
                            'location_code' => $locationCode,
                            'acriss_code' => str($item['acriss_code'])->upper()->toString(),
                            'valid_from' => $validFrom,
                            'valid_to' => $validTo,
                        ],
                        [
                            'office_id' => $office?->id,
                            'office_code' => $officeCode ?? $office?->code,
                            'iata_code' => $iataCode,
                            'vehicle_class' => str($item['vehicle_class'])->upper()->toString(),
                            'available_quantity' => $item['available_quantity'],
                            'status' => $item['status'] ?? 'available',
                            'metadata' => [
                                ...($item['metadata'] ?? []),
                                'source' => 'supplier-service',
                            ],
                        ],
                    );
                })
                ->each->load('supplier:id,name,code', 'office:id,uuid,name,code,iata_code,zone_id')
                ->values();
        });

        return response()->json([
            'data' => VehicleAvailabilityResource::collection($availabilities),
            'meta' => [
                'received' => count($validated['items']),
                'stored' => $availabilities->count(),
            ],
        ], 200);
    }

    /**
     * Find a matching supplier-specific or global office.
     */
    private function findOffice(Supplier $supplier, ?string $officeCode, ?string $iataCode): ?Office
    {
        $query = Office::query()
            ->where(function ($query) use ($supplier) {
                $query
                    ->where('supplier_id', $supplier->id)
                    ->orWhereNull('supplier_id');
            });

        if ($iataCode !== null) {
            $query->where('iata_code', $iataCode);
        } else {
            $query->where('code', $officeCode);
        }

        return $query
            ->orderByRaw('case when supplier_id is null then 1 else 0 end')
            ->first();
    }
}
