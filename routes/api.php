<?php

use App\Http\Controllers\Api\CalculatePromotionController;
use App\Http\Controllers\Api\SupplierServiceBookingController;
use App\Http\Controllers\Api\SupplierServiceVehicleAvailabilityController;
use App\Http\Middleware\EnsureSupplierServiceSource;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureSupplierServiceSource::class, 'throttle:120,1'])
    ->prefix('supplier-service')
    ->name('api.supplier-service.')
    ->group(function (): void {
        Route::post('bookings', SupplierServiceBookingController::class)
            ->name('bookings.store');

        Route::post('vehicle-availability', SupplierServiceVehicleAvailabilityController::class)
            ->name('vehicle-availability.store');
    });

Route::post('promotions/calculate', CalculatePromotionController::class)
    ->middleware('throttle:120,1')
    ->name('api.promotions.calculate');
