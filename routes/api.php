<?php

use App\Http\Controllers\Api\SupplierServiceBookingController;
use App\Http\Controllers\Api\SupplierServiceVehicleAvailabilityController;
use App\Http\Middleware\EnsureSupplierServiceToken;
use Illuminate\Support\Facades\Route;

Route::post('supplier-service/bookings', SupplierServiceBookingController::class)
    ->middleware([EnsureSupplierServiceToken::class, 'throttle:120,1'])
    ->name('api.supplier-service.bookings.store');

Route::post('supplier-service/vehicle-availability', SupplierServiceVehicleAvailabilityController::class)
    ->middleware([EnsureSupplierServiceToken::class, 'throttle:120,1'])
    ->name('api.supplier-service.vehicle-availability.store');
