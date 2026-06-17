<?php

use App\Models\Supplier;

test('supplier service booking endpoint validates requests without token', function () {
    $this->postJson('/api/supplier-service/bookings', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'supplier_code',
            'reservation_code',
            'customer_name',
            'pickup_office_code',
            'dropoff_office_code',
            'pickup_at',
            'dropoff_at',
            'total_amount',
            'currency',
        ]);
});

test('supplier service can create a booking', function () {
    Supplier::factory()->create(['code' => 'DEMO']);

    $this->postJson('/api/supplier-service/bookings', validPayload())
        ->assertCreated()
        ->assertJsonPath('data.supplier.code', 'DEMO')
        ->assertJsonPath('data.reservation_code', 'OUTLET-API-001')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('bookings', [
        'reservation_code' => 'OUTLET-API-001',
        'customer_name' => 'Cliente API',
        'status' => 'pending',
    ]);
});

test('supplier service booking endpoint is idempotent by supplier and reservation code', function () {
    Supplier::factory()->create(['code' => 'DEMO']);

    $this->postJson('/api/supplier-service/bookings', validPayload())
        ->assertCreated();

    $this->postJson('/api/supplier-service/bookings', [
        ...validPayload(),
        'customer_name' => 'Cliente API Actualizado',
        'total_amount' => 325.75,
    ])
        ->assertOk()
        ->assertJsonPath('data.customer_name', 'Cliente API Actualizado')
        ->assertJsonPath('data.total_amount', '325.75');

    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseHas('bookings', [
        'reservation_code' => 'OUTLET-API-001',
        'customer_name' => 'Cliente API Actualizado',
    ]);
});

/**
 * @return array<string, mixed>
 */
function validPayload(): array
{
    return [
        'supplier_code' => 'DEMO',
        'reservation_code' => 'OUTLET-API-001',
        'customer_name' => 'Cliente API',
        'vehicle_class' => 'SUV',
        'pickup_office_code' => 'MEX01',
        'dropoff_office_code' => 'MEX01',
        'pickup_at' => '2026-06-10T10:00:00-06:00',
        'dropoff_at' => '2026-06-12T18:00:00-06:00',
        'total_amount' => 275.50,
        'currency' => 'usd',
        'metadata' => [
            'channel' => 'supplier-service',
        ],
    ];
}
