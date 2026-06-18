<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class RateImportErrorSpreadsheet
{
    /**
     * @param  array{processed: int, successful: int, failed: int}  $summary
     * @param  list<array{row: int, field: string, message: string}>  $errors
     */
    public function create(array $summary, array $errors, ?string $locale = null): string
    {
        $locale = SupportedLocale::normalize($locale ?? app()->getLocale());
        $directory = storage_path('app/import-errors');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'rate-import-errors-'.Str::uuid().'.xlsx';
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $rows = [
            [__('Reporte de errores de importación', locale: $locale)],
            [],
            [__('Total de filas procesadas', locale: $locale), (string) $summary['processed']],
            [__('Total de filas exitosas', locale: $locale), (string) $summary['successful']],
            [__('Total de filas con error', locale: $locale), (string) $summary['failed']],
            [],
            [__('Fila', locale: $locale), __('Campo', locale: $locale), __('Error', locale: $locale)],
            ...array_map(fn (array $error): array => [
                (string) $error['row'],
                $this->label($error['field'], $locale),
                $error['message'],
            ], $errors),
        ];

        $strings = collect($rows)->flatten()->map(fn (?string $value): string => (string) $value)->values();

        $this->addContentTypes($archive);
        $this->addRelationships($archive);
        $this->addWorkbook($archive);
        $this->addSharedStrings($archive, $strings);
        $this->addSheet($archive, $rows);

        $archive->close();

        return $path;
    }

    protected function addContentTypes(ZipArchive $archive): void
    {
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
    }

    protected function addRelationships(ZipArchive $archive): void
    {
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
    }

    protected function addWorkbook(ZipArchive $archive): void
    {
        $archive->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Errors" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);
    }

    /**
     * @param  Collection<int, string>  $strings
     */
    protected function addSharedStrings(ZipArchive $archive, $strings): void
    {
        $sharedStrings = $strings
            ->map(fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>')
            ->implode('');

        $archive->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$strings->count().'" uniqueCount="'.$strings->count().'">'.$sharedStrings.'</sst>');
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function addSheet(ZipArchive $archive, array $rows): void
    {
        $index = 0;
        $sheetRows = collect($rows)
            ->map(function (array $row, int $rowIndex) use (&$index): string {
                $cells = collect($row)
                    ->map(function (?string $value, int $cellIndex) use (&$index, $rowIndex): string {
                        $reference = chr(65 + $cellIndex).($rowIndex + 1);

                        return '<c r="'.$reference.'" t="s"><v>'.($index++).'</v></c>';
                    })
                    ->implode('');

                return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
            })
            ->implode('');

        $archive->addFromString('xl/worksheets/sheet1.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <cols>
    <col min="1" max="1" width="16" customWidth="1"/>
    <col min="2" max="2" width="24" customWidth="1"/>
    <col min="3" max="3" width="54" customWidth="1"/>
  </cols>
  <sheetData>{$sheetRows}</sheetData>
</worksheet>
XML);
    }

    protected function label(string $key, string $locale): string
    {
        $column = config("imports.pricing_template.columns.{$key}", []);
        $translationKey = $column['translation_key'] ?? null;

        return is_string($translationKey) ? __($translationKey, locale: $locale) : $key;
    }
}
