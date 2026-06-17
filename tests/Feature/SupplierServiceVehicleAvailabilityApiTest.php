<?php

use App\Models\Supplier;

test('supplier service vehicle availability endpoint validates requests without token', function () {
    $this->postJson('/api/supplier-service/vehicle-availability', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'supplier_code',
            'items',
        ]);
});

test('supplier service can send vehicle availability', function () {
    Supplier::factory()->create(['code' => 'DEMO']);

    $this->postJson('/api/supplier-service/vehicle-availability', validAvailabilityPayload())
        ->assertOk()
        ->assertJsonPath('meta.received', 3)
        ->assertJsonPath('meta.stored', 3)
        ->assertJsonPath('data.0.supplier.code', 'DEMO')
        ->assertJsonPath('data.0.location_type', 'office')
        ->assertJsonPath('data.0.location_code', 'MEX01')
        ->assertJsonPath('data.0.office_code', 'MEX01')
        ->assertJsonPath('data.0.acriss_code', 'IFAR')
        ->assertJsonPath('data.0.available_quantity', 7)
        ->assertJsonPath('data.2.location_type', 'iata')
        ->assertJsonPath('data.2.location_code', 'CUN');

    $this->assertDatabaseHas('vehicle_availabilities', [
        'location_type' => 'office',
        'location_code' => 'MEX01',
        'office_code' => 'MEX01',
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'available_quantity' => 7,
        'status' => 'available',
    ]);

    $this->assertDatabaseHas('vehicle_availabilities', [
        'location_type' => 'iata',
        'location_code' => 'CUN',
        'iata_code' => 'CUN',
        'vehicle_class' => 'COMPACT',
    ]);
});

test('supplier service vehicle availability endpoint updates matching windows', function () {
    Supplier::factory()->create(['code' => 'DEMO']);

    $this->postJson('/api/supplier-service/vehicle-availability', validAvailabilityPayload())
        ->assertOk();

    $this->postJson('/api/supplier-service/vehicle-availability', [
        'supplier_code' => 'DEMO',
        'items' => [
            [
                ...validAvailabilityPayload()['items'][0],
                'available_quantity' => 2,
                'status' => 'unavailable',
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('meta.received', 1)
        ->assertJsonPath('meta.stored', 1)
        ->assertJsonPath('data.0.available_quantity', 2)
        ->assertJsonPath('data.0.status', 'unavailable');

    $this->assertDatabaseCount('vehicle_availabilities', 3);
    $this->assertDatabaseHas('vehicle_availabilities', [
        'office_code' => 'MEX01',
        'acriss_code' => 'IFAR',
        'available_quantity' => 2,
        'status' => 'unavailable',
    ]);
});

/**
 * @return array<string, mixed>
 */
function validAvailabilityPayload(): array
{
    return [
        'supplier_code' => 'DEMO',
        'items' => [
            [
                'office_code' => 'mex01',
                'vehicle_class' => 'suv',
                'acriss_code' => 'ifar',
                'available_quantity' => 7,
                'valid_from' => '2026-06-10',
                'valid_to' => '2026-06-12',
                'status' => 'available',
                'metadata' => [
                    'batch_id' => 'availability-001',
                ],
            ],
            [
                'office_code' => 'mex01',
                'vehicle_class' => 'compact',
                'acriss_code' => 'ccar',
                'available_quantity' => 3,
                'valid_from' => '2026-06-10',
                'valid_to' => '2026-06-12',
            ],
            [
                'iata_code' => 'cun',
                'vehicle_class' => 'compact',
                'acriss_code' => 'ccar',
                'available_quantity' => 5,
                'valid_from' => '2026-06-13',
                'valid_to' => '2026-06-14',
            ],
        ],
    ];
}
