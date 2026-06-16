<?php

use App\Models\Office;
use App\Models\RateImport;
use App\Models\Supplier;
use App\Models\User;
use App\Support\RateImportSpreadsheet;
use App\Support\RateImportTemplateSpreadsheet;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('imports page shows the latest five uploads', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    RateImport::factory()
        ->count(6)
        ->sequence(fn (Sequence $sequence) => [
            'supplier_id' => $supplier->id,
            'uploaded_by' => $user->id,
            'original_filename' => "rates-{$sequence->index}.xlsx",
            'created_at' => now()->subMinutes(6 - $sequence->index),
        ])
        ->create();

    $this->actingAs($user)
        ->get(route('portal.imports'))
        ->assertOk()
        ->assertSee('Últimas 5 cargas')
        ->assertSee('rates-5.xlsx')
        ->assertSee('rates-1.xlsx')
        ->assertDontSee('rates-0.xlsx');
});

test('imports page exposes a supplier template download', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.imports'))
        ->assertOk()
        ->assertSee('Plantilla por proveedor')
        ->assertSee('data-test="import-template-download"', false);
});

test('admin can activate template download by selecting a supplier', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->assertSee('Selecciona proveedor para descargar')
        ->set('supplierId', $supplier->id)
        ->assertSee('Descargar plantilla Excel')
        ->assertSee(route('portal.imports.template', $supplier->id));
});

test('admin sees active supplier offices with checkboxes after selecting a supplier', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $otherSupplier = Supplier::factory()->create();
    $office = Office::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Cancun Airport',
        'code' => 'CUN01',
        'iata_code' => 'CUN',
        'status' => 'active',
    ]);
    Office::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Inactive Office',
        'status' => 'inactive',
    ]);
    Office::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Other Supplier Office',
        'status' => 'active',
    ]);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('supplierId', $supplier->id)
        ->assertSee('Oficinas registradas')
        ->assertSee('Cancun Airport')
        ->assertSee('CUN01 · CUN')
        ->assertDontSee('Inactive Office')
        ->assertDontSee('Other Supplier Office')
        ->set('selectedOfficeIds', [$office->id])
        ->assertSet('selectedOfficeIds', [$office->id])
        ->assertSee('1 oficinas seleccionadas.');
});

test('imports page shows an empty offices state when selected supplier has no active offices', function () {
    $supplier = Supplier::factory()->create(['code' => 'EMPTY']);
    $user = User::factory()->create([
        'role' => 'admin',
        'supplier_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('supplierId', $supplier->id)
        ->assertSee('Este proveedor no tiene oficinas activas registradas.');
});

test('supplier user sees their active offices on imports page', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $otherSupplier = Supplier::factory()->create(['code' => 'OTHER']);
    Office::factory()->create([
        'supplier_id' => $supplier->id,
        'name' => 'Downtown Office',
        'code' => 'DTO01',
        'status' => 'active',
    ]);
    Office::factory()->create([
        'supplier_id' => $otherSupplier->id,
        'name' => 'Other Supplier Office',
        'code' => 'OTH01',
        'status' => 'active',
    ]);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->assertDontSee('Selecciona proveedor')
        ->assertSee('Proveedor')
        ->assertSee('DEMO')
        ->assertSee('Oficinas registradas')
        ->assertSee('Downtown Office')
        ->assertSee('DTO01')
        ->assertDontSee('Other Supplier Office')
        ->assertDontSee('OTH01');
});

test('supplier rate template can be downloaded', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.imports.template', $supplier->id))
        ->assertOk()
        ->assertDownload('plantilla-precios-DEMO.xlsx');
});

test('rate template header is accepted by the import reader', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'Proveedor Demo',
        'code' => '1410',
    ]);

    $path = app(RateImportTemplateSpreadsheet::class)->create($supplier);

    try {
        $parsed = app(RateImportSpreadsheet::class)->read($path);
    } finally {
        @unlink($path);
    }

    expect($parsed['missing_headers'])->toBe([])
        ->and($parsed['headers'])->toContain('office_code')
        ->and($parsed['rows'])->toHaveCount(2)
        ->and($parsed['rows'][0]['office_code'])->toBe('CUN')
        ->and($parsed['rows'][0]['base_price'])->toBe('1250.00');
});

test('imports page validates the expected excel format before upload', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', excelUpload([
            ['office_code', 'vehicle_class', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'],
            ['CUN', 'SUV', 'IFAR', 'STD', 'USD', '125.50', '2026-06-05', '2026-07-05'],
        ]))
        ->assertSet('formatIsValid', true)
        ->assertSet('detectedRows', 1)
        ->assertSee('Formato validado.');
});

test('imports page rejects excel files with the wrong format', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', excelUpload([
            ['office_code', 'acriss_code'],
            ['CUN', 'IFAR'],
        ]))
        ->assertSet('formatIsValid', false)
        ->assertHasErrors('file')
        ->assertSee('Faltan columnas');
});

test('imports page uploads validated excel files into staging rows', function () {
    Storage::fake();

    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', excelUpload([
            ['office_code', 'vehicle_class', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'],
            ['CUN', 'SUV', 'IFAR', 'STD', 'USD', '125.50', '2026-06-05', '2026-07-05'],
            ['MEX', 'COMPACT', 'CDAR', 'WEEKEND', 'USD', '90.00', '2026-06-05', '2026-07-05'],
        ]))
        ->call('upload')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rate_imports', [
        'supplier_id' => $supplier->id,
        'original_filename' => 'rates.xlsx',
        'status' => 'uploaded',
        'total_rows' => 2,
        'uploaded_by' => $user->id,
    ]);

    $this->assertDatabaseCount('rate_import_rows', 2);
});

/**
 * @param  list<list<string>>  $rows
 */
function excelUpload(array $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent('rates.xlsx', excelContent($rows));
}

/**
 * @param  list<list<string>>  $rows
 */
function excelContent(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $archive->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML);

    $archive->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

    $archive->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML);

    $archive->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);

    $strings = collect($rows)->flatten()->values();
    $sharedStrings = $strings
        ->map(fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>')
        ->implode('');

    $archive->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$strings->count().'" uniqueCount="'.$strings->count().'">'.$sharedStrings.'</sst>');

    $index = 0;
    $sheetRows = collect($rows)
        ->map(function (array $row, int $rowIndex) use (&$index): string {
            $cells = collect($row)
                ->map(function (string $value, int $cellIndex) use (&$index, $rowIndex): string {
                    $reference = chr(65 + $cellIndex).($rowIndex + 1);

                    return '<c r="'.$reference.'" t="s"><v>'.($index++).'</v></c>';
                })
                ->implode('');

            return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        })
        ->implode('');

    $archive->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $archive->close();

    $content = file_get_contents($path);
    unlink($path);

    return $content;
}
