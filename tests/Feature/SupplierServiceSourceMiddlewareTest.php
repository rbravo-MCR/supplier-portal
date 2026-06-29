<?php

use App\Models\Supplier;

test('supplier service endpoints allow requests from configured source ip', function () {
    Supplier::factory()->create(['code' => 'DEMO']);

    config(['supplier.service.allowed_ips' => ['10.10.0.0/16']]);

    $this->postJson('/api/supplier-service/bookings', validSupplierServiceSourcePayload(), [
        'REMOTE_ADDR' => '10.10.2.15',
    ])->assertCreated();
});

test('supplier service endpoints reject requests outside configured source ip', function () {
    Supplier::factory()->create(['code' => 'DEMO']);

    config(['supplier.service.allowed_ips' => ['10.10.0.0/16']]);

    $this->postJson('/api/supplier-service/bookings', validSupplierServiceSourcePayload(), [
        'REMOTE_ADDR' => '203.0.113.10',
    ])->assertForbidden();
});

test('supplier service endpoints fail closed in production when no source allowlist is configured', function () {
    config([
        'app.env' => 'production',
        'supplier.service.allowed_ips' => [],
    ]);

    $this->postJson('/api/supplier-service/bookings', [], [
        'REMOTE_ADDR' => '10.10.2.15',
    ])->assertForbidden();
});

test('supplier service endpoints remain open outside production when no source allowlist is configured', function () {
    config([
        'app.env' => 'testing',
        'supplier.service.allowed_ips' => [],
    ]);

    $this->postJson('/api/supplier-service/bookings', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_code']);
});

/**
 * @return array<string, mixed>
 */
function validSupplierServiceSourcePayload(): array
{
    return [
        'supplier_code' => 'DEMO',
        'reservation_code' => 'OUTLET-API-SOURCE-001',
        'customer_name' => 'Cliente API',
        'vehicle_class' => 'SUV',
        'pickup_office_code' => 'MEX01',
        'dropoff_office_code' => 'MEX01',
        'pickup_at' => '2026-06-10T10:00:00-06:00',
        'dropoff_at' => '2026-06-12T18:00:00-06:00',
        'total_amount' => 275.50,
        'currency' => 'usd',
    ];
}
