<?php

namespace App\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class RateImportSpreadsheet
{
    /**
     * @var list<string>
     */
    /**
     * @return array{headers: list<string>, rows: list<array<string, mixed>>, missing_headers: list<string>, errors: list<array{row: int, field: string, message: string}>, summary: array{processed: int, successful: int, failed: int}}
     */
    public function read(string $path, ?string $locale = null): array
    {
        $locale = SupportedLocale::normalize($locale ?? App::currentLocale());
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException(__('No se pudo leer el archivo Excel.', locale: $locale));
        }

        $sharedStrings = $this->sharedStrings($archive);
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();

        if ($sheet === false) {
            throw new RuntimeException(__('El archivo no contiene la primera hoja esperada.', locale: $locale));
        }

        $rows = $this->sheetRows($sheet, $sharedStrings);

        if ($rows === []) {
            throw new RuntimeException(__('El archivo no contiene filas.', locale: $locale));
        }

        [$headers, $rows, $startRow] = $this->extractHeaderAndRows($rows);
        $missingHeaders = array_values(array_diff($this->requiredHeaders(), $headers));
        $mappedRows = $this->mappedRows($headers, $rows);
        $errors = $this->validateRows($mappedRows, $startRow, $locale);
        $failedRows = collect($errors)->pluck('row')->unique()->count();

        return [
            'headers' => $headers,
            'rows' => $mappedRows,
            'missing_headers' => $missingHeaders,
            'errors' => $errors,
            'summary' => [
                'processed' => count($mappedRows),
                'successful' => count($mappedRows) - $failedRows,
                'failed' => $failedRows,
            ],
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{0: list<string>, 1: list<list<string>>, 2: int}
     */
    protected function extractHeaderAndRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $headers = $this->canonicalHeaders($row);

            if (array_diff($this->requiredHeaders(), $headers) === []) {
                $nextHeaders = $this->canonicalHeaders($rows[$index + 1] ?? []);

                if ($this->isInternalHeaderRow($nextHeaders)) {
                    return [$nextHeaders, array_slice($rows, $index + 2), $index + 3];
                }

                return [$headers, array_slice($rows, $index + 1), $index + 2];
            }
        }

        return [
            $this->canonicalHeaders($rows[0] ?? []),
            array_slice($rows, 1),
            2,
        ];
    }

    protected function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->trim()
            ->ascii()
            ->lower()
            ->replace([' ', '-', '.', ':'], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    /**
     * @param  list<string>  $row
     * @return list<string>
     */
    protected function canonicalHeaders(array $row): array
    {
        $aliases = $this->headerAliases();

        return array_map(function (string $header) use ($aliases): string {
            $normalized = $this->normalizeHeader($header);

            return $aliases[$normalized] ?? $normalized;
        }, $row);
    }

    /**
     * @return array<string, string>
     */
    protected function headerAliases(): array
    {
        $aliases = [];

        foreach ($this->columns() as $key => $column) {
            $aliases[$this->normalizeHeader((string) $key)] = (string) $key;
            $aliases[$this->normalizeHeader((string) ($column['label'] ?? $key))] = (string) $key;

            foreach (($column['aliases'] ?? []) as $localizedAliases) {
                foreach ($localizedAliases as $alias) {
                    $aliases[$this->normalizeHeader((string) $alias)] = (string) $key;
                }
            }
        }

        return $aliases;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function columns(): array
    {
        return config('imports.pricing_template.columns', []);
    }

    /**
     * @return list<string>
     */
    protected function requiredHeaders(): array
    {
        return collect($this->columns())
            ->filter(fn (array $column): bool => (bool) ($column['required'] ?? false))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $headers
     */
    protected function isInternalHeaderRow(array $headers): bool
    {
        return $headers !== []
            && array_values(array_intersect($headers, array_keys($this->columns()))) === $headers;
    }

    /**
     * @return list<string>
     */
    protected function sharedStrings(ZipArchive $archive): array
    {
        $xml = $archive->getFromName('xl/sharedStrings.xml');

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
     * @param  list<string>  $sharedStrings
     * @return list<list<string>>
     */
    protected function sheetRows(string $xml, array $sharedStrings): array
    {
        $document = new SimpleXMLElement($xml);
        $rows = [];

        foreach ($document->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = $this->columnIndex($reference);
                $type = (string) $cell['t'];
                $rawValue = (string) $cell->v;

                $values[$column] = $type === 's'
                    ? ($sharedStrings[(int) $rawValue] ?? '')
                    : $rawValue;
            }

            if ($values !== []) {
                $rows[] = array_values($values);
            }
        }

        return $rows;
    }

    protected function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mappedRows(array $headers, array $rows): array
    {
        return collect($rows)
            ->map(function (array $row) use ($headers): array {
                $mapped = [];

                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $mapped[$header] = trim((string) ($row[$index] ?? ''));
                    }
                }

                return $mapped;
            })
            ->filter(fn (array $row): bool => collect($row)->filter()->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{row: int, field: string, message: string}>
     */
    protected function validateRows(array $rows, int $startRow, string $locale): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            foreach ($this->requiredHeaders() as $header) {
                if (trim((string) ($row[$header] ?? '')) !== '') {
                    continue;
                }

                $errors[] = [
                    'row' => $startRow + $index,
                    'field' => $header,
                    'message' => __('Row :row: :field is required.', [
                        'row' => (string) ($startRow + $index),
                        'field' => $this->label($header, $locale),
                    ], $locale),
                ];
            }
        }

        return $errors;
    }

    protected function label(string $key, string $locale): string
    {
        $column = $this->columns()[$key] ?? [];
        $translationKey = $column['translation_key'] ?? null;

        return is_string($translationKey) ? __($translationKey, locale: $locale) : $key;
    }
}
