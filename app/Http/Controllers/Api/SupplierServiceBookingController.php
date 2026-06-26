<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierServiceBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierServiceBookingController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreSupplierServiceBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        [$booking, $created] = DB::transaction(function () use ($validated): array {
            $supplier = Supplier::query()
                ->where('code', $validated['supplier_code'])
                ->firstOrFail();

            $created = ! Booking::query()
                ->where('supplier_id', $supplier->id)
                ->where('reservation_code', $validated['reservation_code'])
                ->exists();

            $attributes = [
                'supplier_id' => $supplier->id,
                'reservation_code' => $validated['reservation_code'],
                'customer_name' => $validated['customer_name'],
                'vehicle_class' => $validated['vehicle_class'] ?? null,
                'pickup_office_code' => $validated['pickup_office_code'],
                'dropoff_office_code' => $validated['dropoff_office_code'],
                'pickup_at' => $validated['pickup_at'],
                'dropoff_at' => $validated['dropoff_at'],
                'total_amount' => $validated['total_amount'],
                'currency' => str($validated['currency'])->upper()->toString(),
                'metadata' => [
                    ...($validated['metadata'] ?? []),
                    'source' => 'supplier-service',
                ],
            ];

            $now = now();

            DB::table('bookings')->upsert([
                ...$attributes,
                'uuid' => (string) Str::uuid(),
                'status' => 'pending',
                'metadata' => json_encode($attributes['metadata'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ], ['supplier_id', 'reservation_code'], [
                'customer_name',
                'vehicle_class',
                'pickup_office_code',
                'dropoff_office_code',
                'pickup_at',
                'dropoff_at',
                'total_amount',
                'currency',
                'metadata',
                'updated_at',
            ]);

            $booking = Booking::query()
                ->where('supplier_id', $supplier->id)
                ->where('reservation_code', $validated['reservation_code'])
                ->firstOrFail();

            return [$booking, $created];
        });

        return (new BookingResource($booking->load('supplier:id,name,code')))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }
}
