<?php

namespace App\Modules\Import\Application\UseCases;

use App\Models\Currency;
use App\Models\Rate;
use App\Models\RateImport;
use App\Models\RateImportRow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PublishRateImportRows
{
    /**
     * Publish all uploaded staging rows into active rates.
     *
     * @return array{published: int, errors: list<array{row: int, field: string, message: string}>}
     */
    public function handle(RateImport $rateImport, int $userId): array
    {
        $rows = $rateImport->rows()
            ->orderBy('row_number')
            ->get();

        $preparedRows = [];
        $errors = [];

        foreach ($rows as $row) {
            try {
                $preparedRows[$row->id] = $this->normalizeRow($row, $userId);
            } catch (InvalidArgumentException $exception) {
                $errors[] = [
                    'row' => $row->row_number,
                    'field' => 'row',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if ($errors !== []) {
            DB::transaction(function () use ($rateImport, $rows, $errors): void {
                $rows->each(function (RateImportRow $row) use ($errors): void {
                    $rowErrors = collect($errors)
                        ->where('row', $row->row_number)
                        ->values()
                        ->all();

                    $row->update([
                        'errors' => $rowErrors === [] ? null : $rowErrors,
                        'status' => $rowErrors === [] ? 'uploaded' : 'invalid',
                    ]);
                });

                $rateImport->update([
                    'status' => 'failed',
                    'valid_rows' => 0,
                    'invalid_rows' => collect($errors)->pluck('row')->unique()->count(),
                ]);
            });

            return [
                'published' => 0,
                'errors' => $errors,
            ];
        }

        DB::transaction(function () use ($rateImport, $preparedRows, $rows): void {
            foreach ($rows as $row) {
                $data = $preparedRows[$row->id];
                $version = $this->nextVersion(
                    supplierId: $rateImport->supplier_id,
                    officeCode: $data['office_code'],
                    acrissCode: $data['acriss_code'],
                    ratePlanCode: $data['rate_plan_code'],
                );

                Rate::query()->create([
                    'supplier_id' => $rateImport->supplier_id,
                    'office_code' => $data['office_code'],
                    'vehicle_class' => $data['vehicle_class'],
                    'acriss_code' => $data['acriss_code'],
                    'rate_plan_code' => $data['rate_plan_code'],
                    'currency_id' => $data['currency_id'],
                    'base_price' => $data['base_price'],
                    'valid_from' => $data['valid_from'],
                    'valid_to' => $data['valid_to'],
                    'min_days' => null,
                    'max_days' => null,
                    'status' => 'active',
                    'version' => $version,
                    'created_by' => $data['created_by'],
                ]);

                $row->update([
                    'normalized_data' => $data,
                    'errors' => null,
                    'status' => 'published',
                ]);
            }

            $rateImport->update([
                'status' => 'published',
                'valid_rows' => count($preparedRows),
                'invalid_rows' => 0,
            ]);
        });

        return [
            'published' => count($preparedRows),
            'errors' => [],
        ];
    }

    /**
     * @return array{office_code: string, vehicle_class: string, acriss_code: string, rate_plan_code: string, currency_id: int, currency: string, base_price: float, valid_from: string, valid_to: string, created_by: int}
     */
    private function normalizeRow(RateImportRow $row, int $userId): array
    {
        $raw = $row->raw_data ?? [];

        if (! is_array($raw)) {
            throw new InvalidArgumentException(__('Fila :row: datos inválidos.', ['row' => $row->row_number]));
        }

        $officeCode = $this->requiredUpper($raw, 'office_code', $row->row_number);
        $vehicleClass = $this->requiredUpper($raw, 'category_code', $row->row_number);
        $acrissCode = $this->requiredUpper($raw, 'acriss_code', $row->row_number);
        $ratePlanCode = $this->optionalUpper($raw, 'promotion') ?: 'STD';
        $currencyCode = $this->requiredUpper($raw, 'currency', $row->row_number);
        $price = $this->price($raw['price'] ?? null, $row->row_number);
        $validFrom = $this->date($raw['valid_from'] ?? null, $row->row_number, 'valid_from');
        $validTo = $this->date($raw['valid_until'] ?? null, $row->row_number, 'valid_until');

        if ($validTo < $validFrom) {
            throw new InvalidArgumentException(__('Fila :row: la fecha final debe ser mayor o igual a la fecha inicial.', ['row' => $row->row_number]));
        }

        $currency = Currency::query()
            ->active()
            ->where('code', $currencyCode)
            ->first();

        if ($currency === null) {
            throw new InvalidArgumentException(__('Fila :row: la moneda :currency no existe o no está activa.', [
                'row' => $row->row_number,
                'currency' => $currencyCode,
            ]));
        }

        return [
            'office_code' => $officeCode,
            'vehicle_class' => $vehicleClass,
            'acriss_code' => $acrissCode,
            'rate_plan_code' => $ratePlanCode,
            'currency_id' => $currency->id,
            'currency' => $currencyCode,
            'base_price' => $price,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'created_by' => $userId,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function requiredUpper(array $raw, string $key, int $rowNumber): string
    {
        $value = $this->optionalUpper($raw, $key);

        if ($value === '') {
            throw new InvalidArgumentException(__('Fila :row: :field es obligatorio.', [
                'row' => $rowNumber,
                'field' => $key,
            ]));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function optionalUpper(array $raw, string $key): string
    {
        return str((string) ($raw[$key] ?? ''))
            ->trim()
            ->upper()
            ->toString();
    }

    private function price(mixed $value, int $rowNumber): float
    {
        $price = str((string) $value)->trim()->replace(' ', '')->toString();

        if (str_contains($price, ',') && ! str_contains($price, '.')) {
            $price = str_replace(',', '.', $price);
        } else {
            $price = str_replace(',', '', $price);
        }

        if (! is_numeric($price) || (float) $price <= 0) {
            throw new InvalidArgumentException(__('Fila :row: el precio debe ser mayor a cero.', ['row' => $rowNumber]));
        }

        return (float) $price;
    }

    private function date(mixed $value, int $rowNumber, string $field): string
    {
        $date = trim((string) $value);

        if ($date === '') {
            throw new InvalidArgumentException(__('Fila :row: :field es obligatorio.', [
                'row' => $rowNumber,
                'field' => $field,
            ]));
        }

        if (is_numeric($date)) {
            return CarbonImmutable::create(1899, 12, 30)
                ->addDays((int) $date)
                ->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, $date);

                if ($parsed !== false && $parsed->format($format) === $date) {
                    return $parsed->toDateString();
                }
            } catch (InvalidArgumentException) {
                //
            }
        }

        throw new InvalidArgumentException(__('Fila :row: :field no tiene una fecha válida.', [
            'row' => $rowNumber,
            'field' => $field,
        ]));
    }

    private function nextVersion(int $supplierId, string $officeCode, string $acrissCode, string $ratePlanCode): int
    {
        $latestVersion = Rate::query()
            ->where('supplier_id', $supplierId)
            ->where('office_code', $officeCode)
            ->where('acriss_code', $acrissCode)
            ->where('rate_plan_code', $ratePlanCode)
            ->max('version');

        return ((int) $latestVersion) + 1;
    }
}
