<?php

namespace App\Console\Commands;

use App\Models\VehicleCategoryAcrissCode;
use App\Models\VehicleCategoryCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('catalog:import-gps-vehicle-categories {path=docs/gps_categorias.csv : CSV path relative to the project root} {--dry-run : Read and validate without writing to Supplier Portal}')]
#[Description('Import GPS vehicle categories into the master vehicle category catalog.')]
class ImportGpsVehicleCategories extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));

        if (! is_file($path)) {
            $this->error("CSV file not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);

        if ($rows->isEmpty()) {
            $this->error('The GPS vehicle categories CSV does not contain rows.');

            return self::FAILURE;
        }

        $this->components->info("Read {$rows->count()} GPS vehicle categories.");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            VehicleCategoryCatalog::query()->upsert(
                $this->catalogRows($rows)->all(),
                ['code'],
                [
                    'name_es',
                    'name_en',
                    'vehicle_body_type',
                    'passenger_capacity_min',
                    'passenger_capacity_max',
                    'category_family',
                    'transmission_type',
                    'fuel_type',
                    'variant_key',
                    'status',
                    'updated_at',
                ],
            );

            $catalogIds = VehicleCategoryCatalog::query()
                ->whereIn('code', $rows->pluck('categoria')->map(fn (string $code): string => $this->normalizeCode($code))->unique())
                ->pluck('id', 'code');

            VehicleCategoryAcrissCode::query()
                ->whereIn('vehicle_category_catalog_id', $catalogIds->values())
                ->delete();

            $acrissRows = $this->acrissRows($rows, $catalogIds);

            if ($acrissRows->isNotEmpty()) {
                VehicleCategoryAcrissCode::query()->insert($acrissRows->all());
            }
        });

        $this->components->info('Imported GPS vehicle categories into Supplier Portal.');

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Z]:[\\\\\\/]/i', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function readRows(string $path): Collection
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return collect();
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return collect();
        }

        $rows = collect();

        while (($values = fgetcsv($handle)) !== false) {
            $rows->push(array_combine($headers, $values));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function catalogRows(Collection $rows): Collection
    {
        $timestamp = now();

        return $rows
            ->map(fn (array $row): array => [
                'code' => $this->normalizeCode($row['categoria'] ?? ''),
                'name_es' => $this->normalizeText($row['commercial_type_es'] ?? $row['tipo'] ?? ''),
                'name_en' => $this->nullableText($row['commercial_type_en'] ?? null),
                'vehicle_body_type' => $this->normalizeCode($row['vehicle_body_type'] ?? ''),
                'passenger_capacity_min' => $this->nullableInteger($row['passenger_capacity_min'] ?? null),
                'passenger_capacity_max' => $this->nullableInteger($row['passenger_capacity_max'] ?? null),
                'category_family' => $this->nullableCode($row['category_family'] ?? null),
                'transmission_type' => $this->nullableCode($row['category_transmission_type'] ?? null),
                'fuel_type' => $this->nullableCode($row['category_fuel_type'] ?? null),
                'variant_key' => $this->nullableText($row['category_variant_key'] ?? null),
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->unique('code')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $rows
     * @param  Collection<string, int>  $catalogIds
     * @return Collection<int, array<string, mixed>>
     */
    private function acrissRows(Collection $rows, Collection $catalogIds): Collection
    {
        $timestamp = now();

        return $rows
            ->flatMap(function (array $row) use ($catalogIds, $timestamp): array {
                $catalogId = $catalogIds->get($this->normalizeCode($row['categoria'] ?? ''));

                if ($catalogId === null) {
                    return [];
                }

                return collect(explode(',', (string) ($row['acriss_codes'] ?? '')))
                    ->map(fn (string $code): string => $this->normalizeCode($code))
                    ->filter(fn (string $code): bool => strlen($code) === 4)
                    ->unique()
                    ->map(fn (string $code): array => [
                        'vehicle_category_catalog_id' => $catalogId,
                        'code' => $code,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])
                    ->all();
            })
            ->unique(fn (array $row): string => $row['vehicle_category_catalog_id'].'|'.$row['code'])
            ->values();
    }

    private function normalizeCode(?string $code): string
    {
        return Str::of($code ?? '')
            ->squish()
            ->upper()
            ->toString();
    }

    private function nullableCode(?string $code): ?string
    {
        $normalized = $this->normalizeCode($code);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeText(?string $text): string
    {
        return Str::of($text ?? '')
            ->squish()
            ->toString();
    }

    private function nullableText(?string $text): ?string
    {
        $normalized = $this->normalizeText($text);

        return $normalized === '' ? null : $normalized;
    }

    private function nullableInteger(?string $value): ?int
    {
        $normalized = $this->normalizeText($value);

        return ctype_digit($normalized) ? (int) $normalized : null;
    }
}
