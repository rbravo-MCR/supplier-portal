<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\VehicleCategory;
use App\Models\VehicleCategoryAcrissCode;
use App\Models\VehicleCategoryCatalog;
use App\Models\Zone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('fenix:import-catalog {--dry-run : Show source counts without writing}')]
#[Description('Import Fenix catalog data into the supplier portal')]
class ImportFenixCatalogCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = DB::connection('fenix_mysql');

        $sourceCounts = [
            'pais' => $source->table('pais')->count(),
            'rental_destinations' => $source->table('rental_destinations')->count(),
            'rental_zones' => $source->table('rental_zones')->count(),
            'rental_providers' => $source->table('rental_providers')->count(),
            'rental_provider_offices' => $source->table('rental_provider_offices')->count(),
            'vehicle_catalog_categories' => $source->table('vehicle_catalog_categories')->count(),
            'provider_vehicle_catalog_mappings' => $source->table('provider_vehicle_catalog_mappings')->count(),
        ];

        $this->components->info('Source rows');
        $this->table(['table', 'rows'], collect($sourceCounts)->map(
            fn (int $rows, string $table): array => [$table, $rows]
        ));

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run only. No data was written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($source): void {
            $countryIds = $this->importCountries($source);
            $cityIds = $this->importCities($source, $countryIds);
            $zoneIds = $this->importZones($source, $cityIds);
            $supplierIds = $this->importSuppliers($source, $countryIds);
            $this->importOffices($source, $zoneIds, $supplierIds, $cityIds);
            $catalogIds = $this->importVehicleCategoryCatalogs($source);
            $this->importVehicleCategoryAcrissCodes($source, $catalogIds);
            $this->importVehicleCategories($source, $supplierIds, $catalogIds);
        });

        $targetCounts = [
            'countries' => Country::query()->count(),
            'cities' => City::query()->count(),
            'zones' => Zone::query()->count(),
            'suppliers' => Supplier::query()->count(),
            'offices' => Office::query()->count(),
            'vehicle_category_catalogs' => VehicleCategoryCatalog::query()->count(),
            'vehicle_category_acriss_codes' => VehicleCategoryAcrissCode::query()->count(),
            'vehicle_categories' => VehicleCategory::query()->count(),
        ];

        $this->components->info('Target rows');
        $this->table(['table', 'rows'], collect($targetCounts)->map(
            fn (int $rows, string $table): array => [$table, $rows]
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function importCountries(mixed $source): array
    {
        $mxnId = Currency::query()->where('code', 'MXN')->value('id');
        $rows = [];
        $seenIso2 = [];
        $seenIso3 = [];
        $now = now();

        $countries = $source->table('pais')
            ->orderBy('number')
            ->get();

        $countries->each(function (object $row) use (&$rows, &$seenIso2, &$seenIso3, $mxnId, $now): void {
            $iso2 = $this->upperOrNull($row->iso_code);

            if ($iso2 === null || Str::length($iso2) !== 2) {
                return;
            }

            $iso3 = $this->upperOrNull($row->code_alpha_3);
            $iso3Key = $iso3 ?? '';

            if (isset($seenIso2[$iso2]) || ($iso3Key !== '' && isset($seenIso3[$iso3Key]))) {
                return;
            }

            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'iso2' => $iso2,
                'name' => $this->cleanText($row->nombreOficial) ?? $this->cleanText($row->nombre) ?? $iso2,
                'iso3' => $iso3,
                'currency_id' => $iso2 === 'MX' ? $mxnId : null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $seenIso2[$iso2] = true;

            if ($iso3Key !== '') {
                $seenIso3[$iso3Key] = true;
            }
        });

        $source->table('rental_destinations')
            ->select('country_code', 'country_name')
            ->whereNotNull('country_code')
            ->whereNotNull('country_name')
            ->groupBy('country_code', 'country_name')
            ->orderBy('country_code')
            ->get()
            ->each(function (object $row) use (&$rows, &$seenIso2, $now): void {
                $iso2 = $this->upperOrNull($row->country_code);

                if ($iso2 === null || Str::length($iso2) !== 2 || isset($seenIso2[$iso2])) {
                    return;
                }

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'iso2' => $iso2,
                    'name' => $this->cleanText($row->country_name) ?? $iso2,
                    'iso3' => null,
                    'currency_id' => null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $seenIso2[$iso2] = true;
            });

        DB::table('countries')->upsert(
            $rows,
            ['iso2'],
            ['name', 'iso3', 'currency_id', 'status', 'updated_at'],
        );

        $byIso2 = Country::query()->pluck('id', 'iso2');
        $byIso3 = Country::query()->whereNotNull('iso3')->pluck('id', 'iso3');
        $countryIds = [];

        $byIso2->each(function (int $id, string $iso2) use (&$countryIds): void {
            $countryIds[$iso2] = $id;
        });

        $countries->each(function (object $row) use (&$countryIds, $byIso2, $byIso3): void {
            $iso2 = $this->upperOrNull($row->iso_code);
            $iso3 = $this->upperOrNull($row->code_alpha_3);

            if ($iso2 === null) {
                return;
            }

            $id = $byIso2[$iso2] ?? ($iso3 !== null ? ($byIso3[$iso3] ?? null) : null);

            if ($id !== null) {
                $countryIds[$iso2] = $id;
            }
        });

        return $countryIds;
    }

    /**
     * @param  array<string, int>  $countryIds
     * @return array<int, int>
     */
    private function importCities(mixed $source, array $countryIds): array
    {
        $destinations = $source->table('rental_destinations')
            ->orderBy('id')
            ->get();

        $duplicateNames = $this->duplicateKeys(
            $destinations,
            fn (object $row): string => $this->key($this->upperOrNull($row->country_code), $this->cleanText($row->city_name)),
        );

        $rows = [];
        $now = now();

        $destinations->each(function (object $row) use (&$rows, $countryIds, $duplicateNames, $now): void {
            $countryIso = $this->upperOrNull($row->country_code);
            $countryId = $countryIso !== null ? ($countryIds[$countryIso] ?? null) : null;

            if ($countryId === null) {
                return;
            }

            $baseName = $this->cleanText($row->city_name) ?? $this->cleanText($row->display_name) ?? "Destination {$row->id}";
            $nameKey = $this->key($countryIso, $baseName);
            $name = isset($duplicateNames[$nameKey])
                ? "{$baseName} ({$this->destinationCode($row)})"
                : $baseName;

            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'country_id' => $countryId,
                'name' => $name,
                'code' => $this->destinationCode($row),
                'status' => $this->statusFromFlag($row->is_active),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        DB::table('cities')->upsert(
            $rows,
            ['country_id', 'code'],
            ['name', 'status', 'updated_at'],
        );

        $cityIds = [];
        City::query()
            ->whereIn('code', $destinations->map(fn (object $row): string => $this->destinationCode($row))->all())
            ->get(['id', 'code'])
            ->each(function (City $city) use (&$cityIds): void {
                $cityIds[(int) Str::after($city->code, 'FD')] = $city->id;
            });

        return $cityIds;
    }

    /**
     * @param  array<int, int>  $cityIds
     * @return array<int, int>
     */
    private function importZones(mixed $source, array $cityIds): array
    {
        $zones = $source->table('rental_zones')
            ->orderBy('id')
            ->get();

        $duplicateNames = $this->duplicateKeys(
            $zones,
            fn (object $row): string => $this->key((string) $row->destination_id, $this->cleanText($row->name)),
        );

        $rows = [];
        $now = now();

        $zones->each(function (object $row) use (&$rows, $cityIds, $duplicateNames, $now): void {
            $cityId = $cityIds[(int) $row->destination_id] ?? null;

            if ($cityId === null) {
                return;
            }

            $baseName = $this->cleanText($row->name) ?? $this->cleanText($row->city_name) ?? "Zone {$row->id}";
            $nameKey = $this->key((string) $row->destination_id, $baseName);
            $code = "FZ{$row->id}";
            $name = isset($duplicateNames[$nameKey]) ? "{$baseName} ({$code})" : $baseName;

            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'city_id' => $cityId,
                'name' => $name,
                'code' => $code,
                'status' => $this->statusFromFlag($row->is_active),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        DB::table('zones')->upsert(
            $rows,
            ['city_id', 'code'],
            ['name', 'status', 'updated_at'],
        );

        $zoneIds = [];
        Zone::query()
            ->whereIn('code', $zones->map(fn (object $row): string => "FZ{$row->id}")->all())
            ->get(['id', 'code'])
            ->each(function (Zone $zone) use (&$zoneIds): void {
                $zoneIds[(int) Str::after($zone->code, 'FZ')] = $zone->id;
            });

        return $zoneIds;
    }

    /**
     * @param  array<string, int>  $countryIds
     * @return array<int, int>
     */
    private function importSuppliers(mixed $source, array $countryIds): array
    {
        $providers = $source->table('rental_providers')
            ->orderBy('id')
            ->get();

        $rows = [];
        $defaultCountryId = $countryIds['MX'] ?? null;
        $now = now();

        $providers->each(function (object $row) use (&$rows, $defaultCountryId, $now): void {
            $code = $this->supplierCode($row);

            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'name' => $this->cleanText($row->legal_name) ?? $this->cleanText($row->name) ?? $code,
                'country_id' => $defaultCountryId,
                'timezone' => 'America/Merida',
                'integration_type' => (bool) $row->has_api ? Supplier::IntegrationApi : Supplier::IntegrationNone,
                'status' => $this->statusFromFlag($row->is_active),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        DB::table('suppliers')->upsert(
            $rows,
            ['code'],
            ['name', 'country_id', 'timezone', 'integration_type', 'status', 'updated_at'],
        );

        $supplierIds = [];
        Supplier::query()
            ->whereIn('code', $providers->map(fn (object $row): string => $this->supplierCode($row))->all())
            ->get(['id', 'code'])
            ->each(function (Supplier $supplier) use (&$supplierIds, $providers): void {
                $provider = $providers->first(fn (object $row): bool => $this->supplierCode($row) === $supplier->code);

                if ($provider !== null) {
                    $supplierIds[(int) $provider->id] = $supplier->id;
                }
            });

        return $supplierIds;
    }

    /**
     * @param  array<int, int>  $zoneIds
     * @param  array<int, int>  $supplierIds
     * @param  array<int, int>  $cityIds
     */
    private function importOffices(mixed $source, array $zoneIds, array $supplierIds, array $cityIds): void
    {
        $locationCodes = $source->table('rental_provider_location_codes')
            ->select('provider_id', 'provider_office_id', 'external_code', 'external_name', 'external_mcr_code', 'external_location_label', 'station_name')
            ->whereNotNull('provider_office_id')
            ->orderBy('id')
            ->get()
            ->unique('provider_office_id')
            ->keyBy('provider_office_id');

        $duplicateLocationCodes = $this->duplicateKeys(
            $source->table('rental_provider_location_codes')
                ->select('provider_id', 'external_code')
                ->whereNotNull('provider_id')
                ->whereNotNull('external_code')
                ->where('external_code', '<>', '')
                ->get(),
            fn (object $row): string => $this->key((string) $row->provider_id, $this->upperOrNull($row->external_code)),
        );

        $rows = [];
        $seenOfficeCodes = [];
        $now = now();

        $source->table('rental_provider_offices')
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use (&$rows, &$seenOfficeCodes, $zoneIds, $supplierIds, $cityIds, $locationCodes, $duplicateLocationCodes, $now): void {
                $supplierId = $supplierIds[(int) $row->provider_id] ?? null;
                $locationCode = $locationCodes->get($row->id);
                $zoneId = $zoneIds[(int) $row->zone_id]
                    ?? $this->fallbackOfficeZoneId($row, $cityIds, $locationCode);

                if ($supplierId === null || $zoneId === null) {
                    return;
                }

                $code = $this->officeCode($row, $locationCode, $duplicateLocationCodes);
                $codeKey = $this->key((string) $supplierId, $code);

                if (isset($seenOfficeCodes[$codeKey])) {
                    $code = "{$code}-{$row->id}";
                    $codeKey = $this->key((string) $supplierId, $code);
                }

                $seenOfficeCodes[$codeKey] = true;

                $name = $this->cleanText($row->name)
                    ?? $this->cleanText($locationCode?->external_name ?? null)
                    ?? $this->cleanText($locationCode?->station_name ?? null)
                    ?? "Office {$row->id}";

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'supplier_id' => $supplierId,
                    'zone_id' => $zoneId,
                    'name' => $name,
                    'code' => $code,
                    'iata_code' => $this->iataCode($locationCode?->external_mcr_code ?? null),
                    'type' => $this->officeType($row->office_type),
                    'status' => $this->statusFromFlag($row->is_active),
                    'address' => $this->cleanText($row->address),
                    'latitude' => $row->latitude,
                    'longitude' => $row->longitude,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });

        DB::table('offices')->upsert(
            $rows,
            ['supplier_id', 'code'],
            ['zone_id', 'name', 'iata_code', 'type', 'status', 'address', 'latitude', 'longitude', 'updated_at'],
        );
    }

    /**
     * @param  array<int, int>  $cityIds
     */
    private function fallbackOfficeZoneId(object $office, array $cityIds, ?object $locationCode): ?int
    {
        $destinationId = (int) $office->destination_id;
        $cityId = $cityIds[$destinationId] ?? null;

        if ($cityId === null && (int) $office->provider_id === 132) {
            $countryId = Country::query()->where('iso2', 'ES')->value('id');

            if ($countryId !== null) {
                $cityCode = "FD{$destinationId}";
                $now = now();

                DB::table('cities')->upsert(
                    [[
                        'uuid' => (string) Str::uuid(),
                        'country_id' => $countryId,
                        'name' => $this->cleanText($locationCode?->external_name ?? null)
                            ?? $this->cleanText($office->name)
                            ?? "Destination {$destinationId}",
                        'code' => $cityCode,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['country_id', 'code'],
                    ['name', 'status', 'updated_at'],
                );

                $cityId = City::query()
                    ->where('country_id', $countryId)
                    ->where('code', $cityCode)
                    ->value('id');
            }
        }

        if ($cityId === null) {
            return null;
        }

        $code = "FZD{$destinationId}";
        $now = now();

        DB::table('zones')->upsert(
            [[
                'uuid' => (string) Str::uuid(),
                'city_id' => $cityId,
                'name' => 'General',
                'code' => $code,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['city_id', 'code'],
            ['name', 'status', 'updated_at'],
        );

        return Zone::query()
            ->where('city_id', $cityId)
            ->where('code', $code)
            ->value('id');
    }

    /**
     * @return array<int, int>
     */
    private function importVehicleCategoryCatalogs(mixed $source): array
    {
        $now = now();
        $catalogs = $source->table('vehicle_catalog_categories')
            ->orderBy('id')
            ->get();

        $rows = $catalogs
            ->map(fn (object $row): array => [
                'code' => $this->upperOrNull($row->code) ?? "FC{$row->id}",
                'name_es' => $this->plainText($row->operational_type) ?? $this->plainText($row->name) ?? "Categoria {$row->id}",
                'name_en' => $this->plainText($row->name_en),
                'vehicle_body_type' => $this->upperOrNull($row->body_type) ?? 'CAR',
                'passenger_capacity_min' => $this->nullableInteger($row->passenger_capacity),
                'passenger_capacity_max' => $this->nullableInteger($row->passenger_capacity),
                'category_family' => $this->upperOrNull($row->family),
                'transmission_type' => $this->upperOrNull($row->transmission_type),
                'fuel_type' => $this->upperOrNull($row->fuel_type),
                'variant_key' => "fenix:vehicle_catalog_categories:{$row->id}",
                'status' => $this->statusFromText($row->status),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->unique('code')
            ->values()
            ->all();

        DB::table('vehicle_category_catalogs')->upsert(
            $rows,
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

        $idsByCode = VehicleCategoryCatalog::query()->pluck('id', 'code');
        $catalogIds = [];

        $catalogs->each(function (object $row) use (&$catalogIds, $idsByCode): void {
            $code = $this->upperOrNull($row->code) ?? "FC{$row->id}";
            $id = $idsByCode[$code] ?? null;

            if ($id !== null) {
                $catalogIds[(int) $row->id] = $id;
            }
        });

        return $catalogIds;
    }

    /**
     * @param  array<int, int>  $catalogIds
     */
    private function importVehicleCategoryAcrissCodes(mixed $source, array $catalogIds): void
    {
        $now = now();
        $rows = $source->table('provider_vehicle_catalog_mappings')
            ->select('vehicle_catalog_category_id', 'source_acriss', 'source_sipp')
            ->whereNotNull('vehicle_catalog_category_id')
            ->get()
            ->flatMap(function (object $row) use ($catalogIds, $now): array {
                $catalogId = $catalogIds[(int) $row->vehicle_catalog_category_id] ?? null;

                if ($catalogId === null) {
                    return [];
                }

                return collect([$row->source_acriss, $row->source_sipp])
                    ->map(fn (mixed $code): ?string => $this->acrissCode($code))
                    ->filter()
                    ->unique()
                    ->map(fn (string $code): array => [
                        'vehicle_category_catalog_id' => $catalogId,
                        'code' => $code,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();
            })
            ->unique(fn (array $row): string => $row['vehicle_category_catalog_id'].'|'.$row['code'])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        DB::table('vehicle_category_acriss_codes')->upsert(
            $rows,
            ['vehicle_category_catalog_id', 'code'],
            ['updated_at'],
        );
    }

    /**
     * @param  array<int, int>  $supplierIds
     * @param  array<int, int>  $catalogIds
     */
    private function importVehicleCategories(mixed $source, array $supplierIds, array $catalogIds): void
    {
        $now = now();
        $rows = $source->table('provider_vehicle_catalog_mappings')
            ->orderBy('id')
            ->get()
            ->map(function (object $row) use ($supplierIds, $catalogIds, $now): ?array {
                $supplierId = $supplierIds[(int) $row->provider_id] ?? null;

                if ($supplierId === null) {
                    return null;
                }

                $catalogId = $row->vehicle_catalog_category_id === null
                    ? null
                    : ($catalogIds[(int) $row->vehicle_catalog_category_id] ?? null);

                return [
                    'supplier_id' => $supplierId,
                    'vehicle_category_catalog_id' => $catalogId,
                    'supplier_code' => $this->cleanText($row->source_group_id)
                        ?? $this->acrissCode($row->source_acriss)
                        ?? $this->acrissCode($row->source_sipp)
                        ?? "FM{$row->id}",
                    'name' => $this->cleanText($row->source_group_name)
                        ?? $this->cleanText($row->source_model_name)
                        ?? $this->acrissCode($row->source_acriss)
                        ?? "Fenix mapping {$row->id}",
                    'code' => "FM{$row->id}",
                    'acriss_prefix' => $this->acrissCode($row->source_acriss) ?? $this->acrissCode($row->source_sipp),
                    'description' => $this->plainText($row->notes, 1000),
                    'status' => $this->statusFromFlag($row->is_active),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        DB::table('vehicle_categories')->upsert(
            $rows,
            ['supplier_id', 'code'],
            [
                'vehicle_category_catalog_id',
                'supplier_code',
                'name',
                'acriss_prefix',
                'description',
                'status',
                'updated_at',
            ],
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, true>
     */
    private function duplicateKeys(Collection $rows, callable $keyResolver): array
    {
        return $rows
            ->map(fn (object $row): string => $keyResolver($row))
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->map(fn (): bool => true)
            ->all();
    }

    private function cleanText(mixed $value, int $limit = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return Str::limit(Str::squish($text), $limit, '');
    }

    private function plainText(mixed $value, int $limit = 255): ?string
    {
        $text = $this->cleanText(strip_tags((string) $value), $limit);

        return $text === null ? null : html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function upperOrNull(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text === null ? null : Str::upper($text);
    }

    private function destinationCode(object $row): string
    {
        return 'FD'.$row->id;
    }

    private function supplierCode(object $row): string
    {
        return $this->upperOrNull($row->code) ?? 'FP'.$row->id;
    }

    /**
     * @param  array<string, true>  $duplicateLocationCodes
     */
    private function officeCode(object $row, ?object $locationCode, array $duplicateLocationCodes): string
    {
        $externalCode = $this->upperOrNull($locationCode?->external_code ?? null);

        if ($externalCode === null) {
            return 'FO'.$row->id;
        }

        $key = $this->key((string) $row->provider_id, $externalCode);

        return isset($duplicateLocationCodes[$key]) ? "{$externalCode}-{$row->id}" : $externalCode;
    }

    private function iataCode(mixed $value): ?string
    {
        $code = $this->upperOrNull($value);

        if ($code === null) {
            return null;
        }

        return Str::length($code) >= 3 ? Str::substr($code, 0, 3) : null;
    }

    private function acrissCode(mixed $value): ?string
    {
        $code = $this->upperOrNull($value);

        if ($code === null || Str::length($code) !== 4) {
            return null;
        }

        return $code;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function officeType(mixed $value): string
    {
        $type = Str::lower($this->cleanText($value) ?? '');

        return match (true) {
            str_contains($type, 'airport'), str_contains($type, 'aeropuerto') => 'airport',
            str_contains($type, 'downtown'), str_contains($type, 'city') => 'downtown',
            default => 'office',
        };
    }

    private function statusFromFlag(mixed $value): string
    {
        return (bool) $value ? 'active' : 'inactive';
    }

    private function statusFromText(mixed $value): string
    {
        return Str::lower($this->cleanText($value) ?? '') === 'active' ? 'active' : 'inactive';
    }

    private function key(?string ...$parts): string
    {
        return collect($parts)
            ->map(fn (?string $part): string => $part ?? '')
            ->implode('|');
    }
}
