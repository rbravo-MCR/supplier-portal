<?php

use App\Models\Currency;
use App\Models\Office;
use App\Models\Rate;
use App\Models\RateImport;
use App\Models\Supplier;
use App\Models\User;
use App\Support\RateImportSpreadsheet;
use App\Support\RateImportTemplateSpreadsheet;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    App::setLocale('es');
});

test('imports page shows the latest upload', function () {
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
        ->assertSee('Última carga')
        ->assertSee('rates-5.xlsx')
        ->assertDontSee('rates-4.xlsx')
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

test('imports page presents the excel data requirements', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.imports'))
        ->assertOk()
        ->assertSee('Datos requeridos para el Excel')
        ->assertSee('Vehículo')
        ->assertSee('vehicle_name')
        ->assertSee('vehicle_class')
        ->assertSee('Código categoría')
        ->assertSee('category_code')
        ->assertSee('Código ACRISS')
        ->assertSee('acriss_code')
        ->assertSee('Precio')
        ->assertSee('Obligatoria')
        ->assertSee('Promoción')
        ->assertSee('Opcional');
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
        'preferred_locale' => 'en',
    ]);

    $this->actingAs($user)
        ->get(route('portal.imports.template', $supplier->id))
        ->assertOk()
        ->assertDownload('supplier-prices-DEMO-en.xlsx');
});

test('rate template header is accepted by the import reader', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'Proveedor Demo',
        'code' => '1410',
    ]);

    $path = app(RateImportTemplateSpreadsheet::class)->create($supplier, 'en');

    try {
        $parsed = app(RateImportSpreadsheet::class)->read($path, 'en');
        $strings = excelSharedStrings($path);
    } finally {
        @unlink($path);
    }

    expect($parsed['missing_headers'])->toBe([])
        ->and($parsed['headers'])->toContain('office_code')
        ->and($parsed['headers'])->toContain('promotion')
        ->and($parsed['rows'])->toHaveCount(2)
        ->and($parsed['rows'][0]['vehicle_name'])->toBe('SUV')
        ->and($parsed['rows'][0]['category_code'])->toBe('SUV')
        ->and($parsed['rows'][0]['acriss_code'])->toBe('IFAR')
        ->and($parsed['rows'][0]['price'])->toBe('1250.00')
        ->and($strings)->toContain('Vehicle')
        ->and($strings)->toContain('Category code')
        ->and($strings)->toContain('ACRISS code')
        ->and($strings)->toContain('Price')
        ->and($strings)->toContain('Valid from');
});

test('localized supplier templates are generated for every supported locale', function () {
    $supplier = Supplier::factory()->create(['code' => 'DEMO']);
    $paths = app(RateImportTemplateSpreadsheet::class)->ensureLocalizedTemplates($supplier);

    expect(array_keys($paths))->toBe(['es', 'en', 'pt', 'fr', 'it', 'zh', 'ja']);

    foreach ($paths as $locale => $path) {
        expect($path)->toEndWith("supplier-prices-{$locale}.xlsx")
            ->and(is_file($path))->toBeTrue();
    }
});

test('import reader accepts translated excel headers without using them as business keys', function () {
    $path = tempExcelPath([
        ['Office', 'Vehicle', 'Category code', 'ACRISS code', 'Price', 'Currency', 'Valid from', 'Valid until', 'Promotion'],
        ['CUN', 'SUV', 'SUV', 'IFAR', '125.50', 'USD', '06/05/2026', '07/05/2026', 'Weekend'],
    ]);

    try {
        $parsed = app(RateImportSpreadsheet::class)->read($path, 'en');
    } finally {
        @unlink($path);
    }

    expect($parsed['missing_headers'])->toBe([])
        ->and($parsed['headers'])->toBe([
            'office_code',
            'vehicle_name',
            'category_code',
            'acriss_code',
            'price',
            'currency',
            'valid_from',
            'valid_until',
            'promotion',
        ])
        ->and($parsed['rows'][0])->toMatchArray([
            'office_code' => 'CUN',
            'vehicle_name' => 'SUV',
            'category_code' => 'SUV',
            'acriss_code' => 'IFAR',
            'price' => '125.50',
        ]);
});

test('import reader returns translated row validation errors', function (string $locale, string $expectedMessage) {
    $originalLocale = App::currentLocale();
    App::setLocale($locale);

    $path = tempExcelPath([
        [headerForLocale('vehicle_name', $locale), headerForLocale('category_code', $locale), headerForLocale('acriss_code', $locale), headerForLocale('price', $locale), headerForLocale('currency', $locale), headerForLocale('valid_from', $locale), headerForLocale('valid_until', $locale)],
        ['SUV', 'SUV', 'IFAR', '', 'USD', '2026-06-05', '2026-07-05'],
    ]);

    try {
        $parsed = app(RateImportSpreadsheet::class)->read($path, $locale);
    } finally {
        @unlink($path);
        App::setLocale($originalLocale);
    }

    expect($parsed['summary'])->toBe([
        'processed' => 1,
        'successful' => 0,
        'failed' => 1,
    ])->and($parsed['errors'][0]['message'])->toBe($expectedMessage);
})->with([
    'es' => ['es', 'Fila 2: Precio es obligatorio.'],
    'en' => ['en', 'Row 2: Price is required.'],
    'pt' => ['pt', 'Linha 2: Preço é obrigatório.'],
    'fr' => ['fr', 'Ligne 2 : Prix est obligatoire.'],
]);

test('imports page shows translated error summary and downloadable error report', function () {
    App::setLocale('en');

    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
        'preferred_locale' => 'en',
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', supplierExcelUpload($supplier, [
            ['Vehicle', 'Category code', 'ACRISS code', 'Price', 'Currency', 'Valid from', 'Valid until'],
            ['SUV', 'SUV', 'IFAR', '', 'USD', '2026-06-05', '2026-07-05'],
        ]))
        ->assertSet('formatIsValid', false)
        ->assertSet('importSummary', [
            'processed' => 1,
            'successful' => 0,
            'failed' => 1,
        ])
        ->assertSee('The file contains rows with errors.')
        ->assertSee('Total processed rows')
        ->assertSee('Downloadable error file')
        ->assertSee('data-test="import-errors-download"', false);
});

test('imports page validates the expected excel format before upload', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', supplierExcelUpload($supplier, [
            ['office_code', 'vehicle_class', 'category_code', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'],
            ['CUN', 'SUV', 'SUV', 'IFAR', 'STD', 'USD', '125.50', '2026-06-05', '2026-07-05'],
        ]))
        ->assertSet('formatIsValid', true)
        ->assertSet('detectedRows', 1)
        ->assertSee('Formato validado.')
        ->assertSee('type="submit"', false)
        ->assertDontSee('wire:click="commitImport"')
        ->assertSee('data-test="import-processing-overlay"', false);
});

test('imports page rejects xlsx files that were not generated for a supplier', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', excelUpload([
            ['office_code', 'vehicle_class', 'category_code', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'],
            ['CUN', 'SUV', 'SUV', 'IFAR', 'STD', 'USD', '125.50', '2026-06-05', '2026-07-05'],
        ]))
        ->assertSet('formatIsValid', false)
        ->assertHasErrors('file')
        ->assertSee('Solo se permite cargar la plantilla Excel generada para este proveedor.');
});

test('imports page rejects supplier templates generated for another supplier', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', supplierExcelUpload($otherSupplier, [
            ['office_code', 'vehicle_class', 'category_code', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'],
            ['CUN', 'SUV', 'SUV', 'IFAR', 'STD', 'USD', '125.50', '2026-06-05', '2026-07-05'],
        ]))
        ->assertSet('formatIsValid', false)
        ->assertHasErrors('file')
        ->assertSee('El archivo Excel no corresponde al proveedor seleccionado.');
});

test('supplier user cannot import prices by tampering the selected supplier id', function () {
    $supplier = Supplier::factory()->create();
    $otherSupplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('supplierId', $otherSupplier->id)
        ->assertSet('supplierId', $supplier->id)
        ->set('file', supplierExcelUpload($otherSupplier, [
            ['office_code', 'vehicle_class', 'category_code', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'],
            ['CUN', 'SUV', 'SUV', 'IFAR', 'STD', 'USD', '125.50', '2026-06-05', '2026-07-05'],
        ]))
        ->assertSet('formatIsValid', false)
        ->assertHasErrors('file');
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
        ->assertSee('Solo se permite cargar la plantilla Excel generada para este proveedor.');
});

test('imports page uploads validated excel files into staging rows and publishes rates', function () {
    Storage::fake();

    $currency = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['numeric_code' => '840', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_active' => true],
    );
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_admin',
        'supplier_id' => $supplier->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::imports')
        ->set('file', supplierExcelUpload($supplier, [
            ['office_code', 'vehicle_class', 'category_code', 'acriss_code', 'rate_plan_code', 'currency', 'base_price', 'valid_from', 'valid_to'],
            ['CUN', 'SUV', 'SUV', 'IFAR', 'STD', 'USD', '125.50', '2026-06-05', '2026-07-05'],
            ['MEX', 'COMPACT', 'COMPACT', 'CDAR', 'WEEKEND', 'USD', '90.00', '46204', '46234'],
        ]))
        ->call('commitImport')
        ->assertHasNoErrors()
        ->assertSet('lastImportedRows', 2)
        ->assertSet('successMessage', 'Precios cargados correctamente. 2 filas guardadas.')
        ->assertSee('data-test="import-success"', false)
        ->assertSee("supplier-prices-{$supplier->code}.xlsx");

    $this->assertDatabaseHas('rate_imports', [
        'supplier_id' => $supplier->id,
        'original_filename' => "supplier-prices-{$supplier->code}.xlsx",
        'status' => 'published',
        'total_rows' => 2,
        'valid_rows' => 2,
        'invalid_rows' => 0,
        'uploaded_by' => $user->id,
    ]);

    $this->assertDatabaseCount('rate_import_rows', 2);
    $this->assertDatabaseHas('rate_import_rows', [
        'row_number' => 1,
        'status' => 'published',
    ]);
    $this->assertDatabaseHas('rates', [
        'supplier_id' => $supplier->id,
        'office_code' => 'CUN',
        'vehicle_class' => 'SUV',
        'acriss_code' => 'IFAR',
        'rate_plan_code' => 'STD',
        'currency_id' => $currency->id,
        'base_price' => 125.50,
        'status' => 'active',
        'version' => 1,
        'created_by' => $user->id,
    ]);
    $this->assertDatabaseHas('rates', [
        'supplier_id' => $supplier->id,
        'office_code' => 'MEX',
        'vehicle_class' => 'COMPACT',
        'acriss_code' => 'CDAR',
        'rate_plan_code' => 'WEEKEND',
        'currency_id' => $currency->id,
        'base_price' => 90.00,
        'status' => 'active',
    ]);

    $mexRate = Rate::query()
        ->where('supplier_id', $supplier->id)
        ->where('office_code', 'MEX')
        ->where('acriss_code', 'CDAR')
        ->firstOrFail();

    expect(Rate::query()->where('supplier_id', $supplier->id)->count())->toBe(2)
        ->and($mexRate->valid_from->toDateString())->toBe('2026-07-01')
        ->and($mexRate->valid_to->toDateString())->toBe('2026-07-31');
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
function supplierExcelUpload(Supplier $supplier, array $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        "supplier-prices-{$supplier->code}.xlsx",
        excelContent($rows, $supplier),
    );
}

/**
 * @param  list<list<string>>  $rows
 */
function tempExcelPath(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, excelContent($rows));

    return $path;
}

function headerForLocale(string $key, string $locale): string
{
    $column = config("imports.pricing_template.columns.{$key}");

    return $column['aliases'][$locale][0];
}

/**
 * @return list<string>
 */
function excelSharedStrings(string $path): array
{
    $archive = new ZipArchive;
    $archive->open($path);
    $xml = $archive->getFromName('xl/sharedStrings.xml');
    $archive->close();

    if ($xml === false) {
        return [];
    }

    $document = new SimpleXMLElement($xml);
    $strings = [];

    foreach ($document->si as $item) {
        $strings[] = trim((string) $item->t);
    }

    return $strings;
}

/**
 * @param  list<list<string>>  $rows
 */
function excelContent(array $rows, ?Supplier $supplier = null): string
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

    if ($supplier instanceof Supplier) {
        $metadata = [
            'type' => 'supplier_portal_rate_template',
            'supplier_id' => $supplier->id,
            'supplier_uuid' => $supplier->uuid,
            'supplier_code' => $supplier->code,
        ];
        $metadata['signature'] = hash_hmac(
            'sha256',
            implode('|', [
                $metadata['type'],
                (string) $metadata['supplier_id'],
                (string) $metadata['supplier_uuid'],
                (string) $metadata['supplier_code'],
            ]),
            (string) config('app.key'),
        );

        $archive->addFromString('xl/supplier-portal-template.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    $archive->close();

    $content = file_get_contents($path);
    unlink($path);

    return $content;
}
