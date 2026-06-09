<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Models\Zone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('catalog:import-fenix-locations {--source=all : all, global, or mx} {--dry-run : Read and merge records without writing to Supplier Portal}')]
#[Description('Import countries, cities, and zones from Fenix MySQL into Supplier Portal')]
class ImportFenixLocationCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = (string) $this->option('source');

        if (! in_array($source, ['all', 'global', 'mx'], true)) {
            $this->error('The --source option must be all, global, or mx.');

            return self::FAILURE;
        }

        $rows = $this->fetchRows($source);
        $uniqueRows = $this->uniqueRows($rows);

        $this->components->info("Read {$rows->count()} rows from Fenix and merged {$uniqueRows->count()} unique zones.");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($uniqueRows): void {
            $countries = $this->countryRows($uniqueRows);
            Country::query()->upsert($countries->all(), ['iso2'], ['name', 'status', 'updated_at']);

            $countryIds = Country::query()
                ->whereIn('iso2', $countries->pluck('iso2'))
                ->pluck('id', 'iso2');

            $cities = $this->cityRows($uniqueRows, $countryIds);
            $cities->chunk(1000)->each(fn (Collection $chunk) => City::query()->upsert(
                $chunk->all(),
                ['country_id', 'code'],
                ['name', 'status', 'updated_at'],
            ));

            $cityIds = City::query()
                ->whereIn('country_id', $cities->pluck('country_id')->unique())
                ->whereIn('code', $cities->pluck('code')->unique())
                ->get(['id', 'country_id', 'code'])
                ->mapWithKeys(fn (City $city): array => [$city->country_id.'|'.$city->code => $city->id]);

            $zones = $this->zoneRows($uniqueRows, $countryIds, $cityIds);
            $zones->chunk(1000)->each(fn (Collection $chunk) => Zone::query()->upsert(
                $chunk->all(),
                ['city_id', 'code'],
                ['name', 'status', 'updated_at'],
            ));
        });

        $this->components->info('Imported catalog rows into Supplier Portal.');

        return self::SUCCESS;
    }

    /**
     * Fetch zone rows from selected Fenix tables.
     *
     * @return Collection<int, object>
     */
    private function fetchRows(string $source): Collection
    {
        $tables = match ($source) {
            'global' => ['api_zonas'],
            'mx' => ['api_zonas_mx'],
            default => ['api_zonas', 'api_zonas_mx'],
        };

        return collect($tables)
            ->flatMap(fn (string $table): Collection => DB::connection('fenix_mysql')
                ->table($table, 'z')
                ->leftJoin('api_destinos as d', 'd.code', '=', 'z.destination_code')
                ->leftJoin('api_paises as p', 'p.code', '=', 'd.country_code')
                ->select([
                    'z.destination_code',
                    'z.zone_code',
                    'z.name as zone_name',
                    'z.description',
                    'd.name as destination_name',
                    'd.country_code',
                    'p.name as country_name',
                ])
                ->whereNotNull('z.destination_code')
                ->whereNotNull('z.name')
                ->orderBy('z.destination_code')
                ->orderBy('z.zone_code')
                ->get());
    }

    /**
     * Remove duplicated zones from global + MX sources.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function uniqueRows(Collection $rows): Collection
    {
        return $rows
            ->unique(fn (object $row): string => implode('|', [
                $this->normalizeCode($row->destination_code, 10),
                $row->zone_code === null ? '' : (string) $row->zone_code,
                Str::of($row->zone_name)->squish()->lower()->toString(),
            ]))
            ->values();
    }

    /**
     * Build country rows for bulk upsert.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function countryRows(Collection $rows): Collection
    {
        $timestamp = now();

        return $rows
            ->map(fn (object $row): array => [
                'uuid' => (string) Str::uuid(),
                'name' => $row->country_name ?: $row->country_code ?: 'Unknown',
                'iso2' => $this->normalizeCode($row->country_code ?: 'ZZ', 2),
                'iso3' => null,
                'status' => 'active',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->unique('iso2')
            ->values();
    }

    /**
     * Build city rows for bulk upsert.
     *
     * @param  Collection<int, object>  $rows
     * @param  Collection<string, int>  $countryIds
     * @return Collection<int, array<string, mixed>>
     */
    private function cityRows(Collection $rows, Collection $countryIds): Collection
    {
        $timestamp = now();

        return $rows
            ->map(function (object $row) use ($countryIds, $timestamp): array {
                $countryIso2 = $this->normalizeCode($row->country_code ?: 'ZZ', 2);

                return [
                    'uuid' => (string) Str::uuid(),
                    'country_id' => $countryIds->get($countryIso2),
                    'name' => $row->destination_name ?: $row->destination_code,
                    'code' => $this->normalizeCode($row->destination_code, 10),
                    'status' => 'active',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->filter(fn (array $row): bool => $row['country_id'] !== null)
            ->unique(fn (array $row): string => $row['country_id'].'|'.$row['code'])
            ->values();
    }

    /**
     * Build zone rows for bulk upsert.
     *
     * @param  Collection<int, object>  $rows
     * @param  Collection<string, int>  $countryIds
     * @param  Collection<string, int>  $cityIds
     * @return Collection<int, array<string, mixed>>
     */
    private function zoneRows(Collection $rows, Collection $countryIds, Collection $cityIds): Collection
    {
        $timestamp = now();

        return $rows
            ->map(function (object $row) use ($countryIds, $cityIds, $timestamp): array {
                $countryIso2 = $this->normalizeCode($row->country_code ?: 'ZZ', 2);
                $cityCode = $this->normalizeCode($row->destination_code, 10);
                $cityId = $cityIds->get($countryIds->get($countryIso2).'|'.$cityCode);

                return [
                    'uuid' => (string) Str::uuid(),
                    'city_id' => $cityId,
                    'name' => $this->normalizeName($row->zone_name),
                    'code' => $row->zone_code === null ? null : (string) $row->zone_code,
                    'status' => 'active',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->filter(fn (array $row): bool => $row['city_id'] !== null)
            ->unique(fn (array $row): string => $row['city_id'].'|'.($row['code'] ?? Str::of($row['name'])->lower()->toString()))
            ->values();
    }

    private function normalizeCode(?string $code, int $limit): string
    {
        return Str::of($code ?? '')
            ->squish()
            ->upper()
            ->limit($limit, '')
            ->toString();
    }

    private function normalizeName(?string $name): string
    {
        return Str::of($name ?? '')
            ->squish()
            ->toString();
    }
}
