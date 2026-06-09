<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierServiceBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SupplierServiceBookingController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreSupplierServiceBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = DB::transaction(function () use ($validated): Booking {
            $supplier = Supplier::query()
                ->where('code', $validated['supplier_code'])
                ->firstOrFail();

            $booking = Booking::query()
                ->where('supplier_id', $supplier->id)
                ->where('reservation_code', $validated['reservation_code'])
                ->first();

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

            if ($booking instanceof Booking) {
                $booking->update($attributes);

                return $booking->refresh();
            }

            return Booking::query()->create([
                ...$attributes,
                'status' => 'pending',
            ]);
        });

        return (new BookingResource($booking->load('supplier:id,name,code')))
            ->response()
            ->setStatusCode($booking->wasRecentlyCreated ? 201 : 200);
    }
}
