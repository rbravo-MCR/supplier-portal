<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculatePromotionRequest;
use App\Models\Supplier;
use App\Modules\Promotions\Application\DTOs\CalculatePriceData;
use App\Modules\Promotions\Application\UseCases\CalculateBookingPrice;
use Illuminate\Http\JsonResponse;

class CalculatePromotionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CalculatePromotionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $supplier = Supplier::query()
            ->where('code', $validated['supplier_code'])
            ->firstOrFail();

        $result = app(CalculateBookingPrice::class)->handle(new CalculatePriceData(
            supplierId: $supplier->id,
            officeCode: $validated['office_code'],
            acrissCode: $validated['acriss_code'],
            pickupAt: $validated['pickup_at'],
            dropoffAt: $validated['dropoff_at'],
            baseAmount: (float) $validated['base_amount'],
            currency: str($validated['currency'])->upper()->toString(),
        ));

        return response()->json($result);
    }
}
