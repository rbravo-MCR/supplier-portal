<?php

use App\Models\Rate;
use App\Models\RateImport;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Import\Application\DTOs\CreateRateImportData;
use App\Modules\Import\Application\UseCases\CreateRateImport;
use App\Modules\Import\Domain\Exceptions\RateImportCannotBeEmpty;

test('supplier rate import creates staging records and does not publish rates', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);

    $rateImport = app(CreateRateImport::class)->handle(new CreateRateImportData(
        originalFilename: 'rates.xlsx',
        storedPath: 'imports/rates.xlsx',
        uploadedBy: $user->id,
        rows: [
            [
                'office_code' => 'CUN',
                'acriss_code' => 'IFAR',
                'rate_plan_code' => 'STD',
                'base_price' => 100,
            ],
            [
                'office_code' => 'MEX',
                'acriss_code' => 'ECAR',
                'rate_plan_code' => 'STD',
                'base_price' => 80,
            ],
        ],
    ));

    expect($rateImport->supplier_id)->toBe($supplier->id)
        ->and($rateImport->status)->toBe('uploaded')
        ->and($rateImport->total_rows)->toBe(2)
        ->and($rateImport->valid_rows)->toBe(0)
        ->and($rateImport->invalid_rows)->toBe(0);

    $this->assertDatabaseCount('rate_import_rows', 2);
    $this->assertDatabaseHas('rate_import_rows', [
        'rate_import_id' => $rateImport->id,
        'row_number' => 1,
        'status' => 'uploaded',
    ]);

    expect(Rate::query()->count())->toBe(0);
});

test('rate import ignores supplier id from request', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);
    request()->merge(['supplier_id' => $otherSupplier->id]);

    $rateImport = app(CreateRateImport::class)->handle(new CreateRateImportData(
        originalFilename: 'rates.xlsx',
        storedPath: 'imports/rates.xlsx',
        uploadedBy: $user->id,
        rows: [
            ['office_code' => 'CUN'],
        ],
    ));

    expect($rateImport->supplier_id)->toBe($supplier->id);

    $this->assertDatabaseMissing('rate_imports', [
        'supplier_id' => $otherSupplier->id,
    ]);
});

test('rate import cannot be created without rows', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user);

    app(CreateRateImport::class)->handle(new CreateRateImportData(
        originalFilename: 'rates.xlsx',
        storedPath: 'imports/rates.xlsx',
        uploadedBy: $user->id,
        rows: [],
    ));
})->throws(RateImportCannotBeEmpty::class);

test('rate import model exposes rows relation', function () {
    $rateImport = RateImport::factory()
        ->hasRows(2)
        ->create();

    expect($rateImport->rows()->count())->toBe(2);
});
