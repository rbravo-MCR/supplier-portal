<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class RateImportSpreadsheet
{
    /**
     * @var list<string>
     */
    public const REQUIRED_HEADERS = [
        'office_code',
        'vehicle_class',
        'acriss_code',
        'rate_plan_code',
        'currency',
        'base_price',
        'valid_from',
        'valid_to',
    ];

    /**
     * @return array{headers: list<string>, rows: list<array<string, mixed>>, missing_headers: list<string>}
     */
    public function read(string $path): array
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('No se pudo leer el archivo Excel.');
        }

        $sharedStrings = $this->sharedStrings($archive);
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();

        if ($sheet === false) {
            throw new RuntimeException('El archivo no contiene la primera hoja esperada.');
        }

        $rows = $this->sheetRows($sheet, $sharedStrings);

        if ($rows === []) {
            throw new RuntimeException('El archivo no contiene filas.');
        }

        [$headers, $rows] = $this->extractHeaderAndRows($rows);
        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        return [
            'headers' => $headers,
            'rows' => $this->mappedRows($headers, $rows),
            'missing_headers' => $missingHeaders,
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    protected function extractHeaderAndRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $headers = array_map($this->normalizeHeader(...), $row);

            if (array_diff(self::REQUIRED_HEADERS, $headers) === []) {
                return [$headers, array_slice($rows, $index + 1)];
            }
        }

        return [
            array_map($this->normalizeHeader(...), $rows[0] ?? []),
            array_slice($rows, 1),
        ];
    }

    protected function normalizeHeader(string $header): string
    {
        return str($header)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();
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
}
