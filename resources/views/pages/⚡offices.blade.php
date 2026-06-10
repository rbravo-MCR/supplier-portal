<?php

use App\Concerns\WithSearchableCatalog;
use App\Concerns\RemembersModelRows;
use App\Models\City;
use App\Models\Country;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\Zone;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Oficinas')] class extends Component {
    use WithPagination, WithSearchableCatalog, RemembersModelRows;

    public ?int $supplierId = null;

    public ?int $countryId = null;

    public ?int $cityId = null;

    public ?int $zoneId = null;

    public ?int $filterCountryId = null;

    public ?int $filterCityId = null;

    public ?int $filterZoneId = null;

    public string $countrySearch = '';

    public string $citySearch = '';

    public string $zoneSearch = '';

    public string $filterCountrySearch = '';

    public string $filterCitySearch = '';

    public string $filterZoneSearch = '';

    public string $name = '';

    public string $code = '';

    public string $iataCode = '';

    public string $type = 'office';

    public string $status = 'active';

    public string $address = '';

    public string $search = '';

    public function mount(): void
    {
        $this->supplierId = Auth::user()->supplier_id;
        $this->countryId = $this->defaultCountryId();
    }

    public function updatedCountryId(): void
    {
        $this->cityId = null;
        $this->zoneId = null;
        $this->citySearch = '';
        $this->zoneSearch = '';
    }

    public function updatedCityId(): void
    {
        $this->zoneId = null;
        $this->zoneSearch = '';
    }

    public function updatedFilterCountryId(): void
    {
        $this->filterCityId = null;
        $this->filterZoneId = null;
        $this->filterCitySearch = '';
        $this->filterZoneSearch = '';
        $this->resetPage();
    }

    public function updatedFilterCityId(): void
    {
        $this->filterZoneId = null;
        $this->filterZoneSearch = '';
        $this->resetPage();
    }

    public function updatedFilterZoneId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCountrySearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCitySearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterZoneSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['filterCountryId', 'filterCityId', 'filterZoneId', 'filterCountrySearch', 'filterCitySearch', 'filterZoneSearch', 'search']);
        $this->resetPage();
    }

    #[Computed]
    public function currentSupplier(): ?Supplier
    {
        $supplierId = Auth::user()->supplier_id;

        if (! $supplierId) {
            return null;
        }

        return Supplier::query()
            ->select(['id', 'name', 'code'])
            ->find($supplierId);
    }

    public function save(): void
    {
        $supplierId = Auth::user()->supplier_id ?: $this->supplierId;

        $validated = $this->validate([
            'supplierId' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'zoneId' => ['required', 'integer', Rule::exists('zones', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('offices', 'code')->where('supplier_id', $supplierId),
            ],
            'iataCode' => ['nullable', 'string', 'size:3'],
            'type' => ['required', Rule::in(['office', 'airport', 'station'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        Office::query()->create([
            'supplier_id' => $supplierId,
            'zone_id' => $validated['zoneId'],
            'name' => $validated['name'],
            'code' => str($validated['code'])->upper()->toString(),
            'iata_code' => $validated['iataCode'] !== '' ? str($validated['iataCode'])->upper()->toString() : null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'address' => $validated['address'] ?: null,
        ]);

        $this->reset(['countryId', 'cityId', 'zoneId', 'countrySearch', 'citySearch', 'zoneSearch', 'name', 'code', 'iataCode', 'address']);
        $this->supplierId = Auth::user()->supplier_id;
        $this->countryId = $this->defaultCountryId();
        $this->type = 'office';
        $this->status = 'active';
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Oficina creada.'));
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'zoneId.required' => __('Selecciona una zona antes de crear la oficina.'),
            'name.required' => __('Captura el nombre de la oficina.'),
            'code.required' => __('Captura el código de oficina.'),
            'code.unique' => __('Ese código ya existe para este proveedor.'),
            'iataCode.size' => __('El IATA debe tener 3 caracteres.'),
        ];
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function suppliers(): Collection
    {
        return $this->rememberModels('suppliers.active', Supplier::class, function () {
            return Supplier::query()
                ->select(['id', 'name', 'code'])
                ->active()
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  \Closure(): Collection<int, \Illuminate\Database\Eloquent\Model>  $callback
     * @return Collection<int, mixed>
     */
    private function rememberCatalog(string $key, string $search, string $modelClass, \Closure $callback): Collection
    {
        if ($search !== '') {
            return $callback();
        }

        return $this->rememberModels($key, $modelClass, $callback);
    }

    /**
     * @return Collection<int, Country>
     */
    #[Computed]
    public function countries(): Collection
    {
        return $this->rememberCatalog('countries.active', $this->countrySearch, Country::class, function () {
            return Country::query()
                ->select(['id', 'name', 'iso2'])
                ->active()
                ->when($this->countrySearch !== '', function (Builder $query): void {
                    $query->search($this->countrySearch);
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        });
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    #[Computed]
    public function countryOptions(): Collection
    {
        return $this->countries
            ->map(fn (Country $country): array => [
                'value' => $country->id,
                'iso2' => $country->iso2,
                'label' => $this->countryLabel($country),
            ]);
    }

    /**
     * @return Collection<int, Country>
     */
    #[Computed]
    public function filterCountries(): Collection
    {
        return $this->rememberCatalog('filter.countries.active', $this->filterCountrySearch, Country::class, function () {
            return Country::query()
                ->select(['id', 'name', 'iso2'])
                ->active()
                ->when($this->filterCountrySearch !== '', function (Builder $query): void {
                    $query->search($this->filterCountrySearch);
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        });
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    #[Computed]
    public function filterCountryOptions(): Collection
    {
        return $this->filterCountries
            ->map(fn (Country $country): array => [
                'value' => $country->id,
                'iso2' => $country->iso2,
                'label' => $this->countryLabel($country),
            ]);
    }

    /**
     * @return Collection<int, City>
     */
    #[Computed]
    public function cities(): Collection
    {
        return $this->rememberCatalog("cities.active.{$this->countryId}", $this->citySearch, City::class, function () {
            return City::query()
                ->select(['id', 'country_id', 'name', 'code'])
                ->forCountry($this->countryId)
                ->when($this->citySearch !== '', function (Builder $query): void {
                    $query->search($this->citySearch);
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        });
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    #[Computed]
    public function cityOptions(): Collection
    {
        return $this->cities
            ->map(fn (City $city): array => [
                'value' => $city->id,
                'label' => "{$city->name} · {$city->code}",
            ]);
    }

    /**
     * @return Collection<int, Zone>
     */
    #[Computed]
    public function zones(): Collection
    {
        return $this->rememberCatalog("zones.active.{$this->countryId}.{$this->cityId}", $this->zoneSearch, Zone::class, function () {
            return Zone::query()
                ->select(['id', 'city_id', 'name', 'code'])
                ->forCity($this->cityId)
                ->when(! $this->cityId && $this->countryId, function (Builder $query): void {
                    $query->forCountry($this->countryId);
                })
                ->when($this->zoneSearch !== '', function (Builder $query): void {
                    $query->search($this->zoneSearch);
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        });
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    #[Computed]
    public function zoneOptions(): Collection
    {
        return $this->zones
            ->map(fn (Zone $zone): array => [
                'value' => $zone->id,
                'label' => "{$zone->name} · ".($zone->code ?? '-'),
            ]);
    }

    /**
     * @return Collection<int, City>
     */
    #[Computed]
    public function filterCities(): Collection
    {
        return $this->rememberCatalog("filter.cities.{$this->filterCountryId}", $this->filterCitySearch, City::class, function () {
            return City::query()
                ->select(['id', 'country_id', 'name', 'code'])
                ->forCountry($this->filterCountryId)
                ->when($this->filterCitySearch !== '', function (Builder $query): void {
                    $query->search($this->filterCitySearch);
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        });
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    #[Computed]
    public function filterCityOptions(): Collection
    {
        return $this->filterCities
            ->map(fn (City $city): array => [
                'value' => $city->id,
                'label' => "{$city->name} · {$city->code}",
            ]);
    }

    /**
     * @return Collection<int, Zone>
     */
    #[Computed]
    public function filterZones(): Collection
    {
        return $this->rememberCatalog("filter.zones.{$this->filterCountryId}.{$this->filterCityId}", $this->filterZoneSearch, Zone::class, function () {
            return Zone::query()
                ->select(['id', 'city_id', 'name', 'code'])
                ->forCity($this->filterCityId)
                ->when(! $this->filterCityId && $this->filterCountryId, function (Builder $query): void {
                    $query->forCountry($this->filterCountryId);
                })
                ->when($this->filterZoneSearch !== '', function (Builder $query): void {
                    $query->search($this->filterZoneSearch);
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        });
    }

    /**
     * @return Collection<int, array{value: int, label: string}>
     */
    #[Computed]
    public function filterZoneOptions(): Collection
    {
        return $this->filterZones
            ->map(fn (Zone $zone): array => [
                'value' => $zone->id,
                'label' => "{$zone->name} · ".($zone->code ?? '-'),
            ]);
    }

    #[Computed]
    public function selectedCountryLabel(): ?string
    {
        return Cache::remember("selected.country.{$this->countryId}", 3600, function (): ?string {
            $country = $this->countryId ? Country::query()->select(['id', 'name', 'iso2'])->find($this->countryId) : null;

            return $country ? $this->countryLabel($country) : null;
        });
    }

    #[Computed]
    public function selectedCountryIso2(): ?string
    {
        return Cache::remember("selected.country.iso2.{$this->countryId}", 3600, function (): ?string {
            return $this->countryId ? Country::query()->whereKey($this->countryId)->value('iso2') : null;
        });
    }

    #[Computed]
    public function selectedCityLabel(): ?string
    {
        return Cache::remember("selected.city.{$this->cityId}", 3600, function (): ?string {
            $city = $this->cityId ? City::query()->select(['id', 'name', 'code'])->find($this->cityId) : null;

            return $city ? "{$city->name} · {$city->code}" : null;
        });
    }

    #[Computed]
    public function selectedZoneLabel(): ?string
    {
        return Cache::remember("selected.zone.{$this->zoneId}", 3600, function (): ?string {
            $zone = $this->zoneId ? Zone::query()->select(['id', 'name', 'code'])->find($this->zoneId) : null;

            return $zone ? "{$zone->name} · ".($zone->code ?? '-') : null;
        });
    }

    #[Computed]
    public function selectedFilterCountryLabel(): ?string
    {
        return Cache::remember("selected.filter.country.{$this->filterCountryId}", 3600, function (): ?string {
            $country = $this->filterCountryId ? Country::query()->select(['id', 'name', 'iso2'])->find($this->filterCountryId) : null;

            return $country ? $this->countryLabel($country) : null;
        });
    }

    #[Computed]
    public function selectedFilterCountryIso2(): ?string
    {
        return Cache::remember("selected.filter.country.iso2.{$this->filterCountryId}", 3600, function (): ?string {
            return $this->filterCountryId ? Country::query()->whereKey($this->filterCountryId)->value('iso2') : null;
        });
    }

    #[Computed]
    public function selectedFilterCityLabel(): ?string
    {
        return Cache::remember("selected.filter.city.{$this->filterCityId}", 3600, function (): ?string {
            $city = $this->filterCityId ? City::query()->select(['id', 'name', 'code'])->find($this->filterCityId) : null;

            return $city ? "{$city->name} · {$city->code}" : null;
        });
    }

    #[Computed]
    public function selectedFilterZoneLabel(): ?string
    {
        return Cache::remember("selected.filter.zone.{$this->filterZoneId}", 3600, function (): ?string {
            $zone = $this->filterZoneId ? Zone::query()->select(['id', 'name', 'code'])->find($this->filterZoneId) : null;

            return $zone ? "{$zone->name} · ".($zone->code ?? '-') : null;
        });
    }

    private function countryLabel(Country $country): string
    {
        return "{$country->name} · {$country->iso2}";
    }

    private function defaultCountryId(): ?int
    {
        return Cache::remember('default.country.mx', 3600, function () {
            return Country::query()
                ->where('iso2', 'MX')
                ->active()
                ->value('id');
        });
    }

    #[Computed]
    public function offices(): LengthAwarePaginator
    {
        return Office::query()
            ->with([
                'supplier:id,name,code',
                'zone:id,city_id,name,code',
                'zone.city:id,country_id,name,code',
                'zone.city.country:id,name,iso2',
            ])
            ->forSupplier(Auth::user()->supplier_id)
            ->when($this->filterZoneId, fn (Builder $query, int $zoneId) => $query->where('zone_id', $zoneId))
            ->when($this->filterCityId, function (Builder $query): void {
                $query->whereIn('zone_id', Zone::query()
                    ->select('id')
                    ->where('city_id', $this->filterCityId)
                );
            })
            ->when($this->filterCountryId, function (Builder $query): void {
                $cityIds = City::query()
                    ->select('id')
                    ->where('country_id', $this->filterCountryId);

                $query->whereIn('zone_id', Zone::query()
                    ->select('id')
                    ->whereIn('city_id', $cityIds)
                );
            })
            ->when($this->search !== '', function (Builder $query): void {
                $query->search($this->search);
            })
            ->orderBy('name')
            ->paginate(10);
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Oficinas') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Administra localidades de oficina por proveedor y vincúlalas al catálogo de país, ciudad y zona.') }}
        </flux:text>
    </div>

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @if (! Auth::user()->supplier_id)
                <flux:select wire:model="supplierId" :label="__('Proveedor')" data-test="office-supplier">
                    <flux:select.option value="">{{ __('Oficina global') }}</flux:select.option>
                    @foreach ($this->suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <div class="flex flex-col gap-2" data-test="office-current-supplier">
                    <flux:text class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Proveedor') }}</flux:text>
                    <div class="flex min-h-10 items-center rounded-md border border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                        {{ $this->currentSupplier?->name }} · {{ $this->currentSupplier?->code }}
                    </div>
                </div>
            @endif

            <x-portal-searchable-select
                :label="__('País')"
                property="countryId"
                search-property="countrySearch"
                :options="$this->countryOptions"
                :selected-label="$this->selectedCountryLabel"
                :selected-iso2="$this->selectedCountryIso2"
                :placeholder="__('Filtrar país')"
                search-placeholder="México, MX"
                :empty="__('Sin países')"
                data-test="office-country"
            />

            <x-portal-searchable-select
                wire:key="office-city-{{ $countryId ?? 'all' }}"
                :label="__('Ciudad')"
                property="cityId"
                search-property="citySearch"
                :options="$this->cityOptions"
                :selected-label="$this->selectedCityLabel"
                :placeholder="__('Filtrar ciudad')"
                search-placeholder="Cancun, CUN"
                :empty="__('Sin ciudades')"
                data-test="office-city"
            />

            <x-portal-searchable-select
                wire:key="office-zone-{{ $countryId ?? 'all' }}-{{ $cityId ?? 'all' }}"
                :label="__('Zona')"
                property="zoneId"
                search-property="zoneSearch"
                :options="$this->zoneOptions"
                :selected-label="$this->selectedZoneLabel"
                :placeholder="__('Selecciona zona')"
                search-placeholder="Hotel, 10"
                :empty="__('Sin zonas')"
                data-test="office-zone"
            />

            <div>
                <flux:input wire:model="name" :label="__('Nombre')" placeholder="Cancun Airport" data-test="office-name" />
                @error('name')
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <flux:input wire:model="code" :label="__('Código oficina')" placeholder="CUN01" data-test="office-code" />
                @error('code')
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <flux:input wire:model="iataCode" :label="__('IATA')" placeholder="CUN" maxlength="3" data-test="office-iata" />
                @error('iataCode')
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <flux:select wire:model="type" :label="__('Tipo')" data-test="office-type">
                <flux:select.option value="office">{{ __('Oficina') }}</flux:select.option>
                <flux:select.option value="airport">{{ __('Aeropuerto') }}</flux:select.option>
                <flux:select.option value="station">{{ __('Estación') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model="status" :label="__('Estado')" data-test="office-status">
                <flux:select.option value="active">{{ __('Activo') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactivo') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="mt-4">
            <flux:input wire:model="address" :label="__('Dirección')" placeholder="Terminal 2, zona de arrendadoras" data-test="office-address" />
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button
                type="submit"
                variant="primary"
                icon="plus"
                wire:loading.attr="disabled"
                wire:target="countryId,cityId,zoneId,save"
                data-test="office-submit"
            >
                <span wire:loading.remove wire:target="save">{{ __('Crear oficina') }}</span>
                <span wire:loading wire:target="save">{{ __('Creando...') }}</span>
            </flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3">
            <div>
                <flux:heading>{{ __('Directorio de oficinas') }}</flux:heading>
                <flux:text>{{ __('Los filtros por país, ciudad y zona son opcionales.') }}</flux:text>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <x-portal-searchable-select
                    :label="__('País')"
                    property="filterCountryId"
                    search-property="filterCountrySearch"
                    :options="$this->filterCountryOptions"
                    :selected-label="$this->selectedFilterCountryLabel"
                    :selected-iso2="$this->selectedFilterCountryIso2"
                    :placeholder="__('Todos')"
                    search-placeholder="México, MX"
                    :empty="__('Sin países')"
                    data-test="office-filter-country"
                />

                <x-portal-searchable-select
                    wire:key="office-filter-city-{{ $filterCountryId ?? 'all' }}"
                    :label="__('Ciudad')"
                    property="filterCityId"
                    search-property="filterCitySearch"
                    :options="$this->filterCityOptions"
                    :selected-label="$this->selectedFilterCityLabel"
                    :placeholder="__('Todas')"
                    search-placeholder="Cancun, CUN"
                    :empty="__('Sin ciudades')"
                    data-test="office-filter-city"
                />

                <x-portal-searchable-select
                    wire:key="office-filter-zone-{{ $filterCountryId ?? 'all' }}-{{ $filterCityId ?? 'all' }}"
                    :label="__('Zona')"
                    property="filterZoneId"
                    search-property="filterZoneSearch"
                    :options="$this->filterZoneOptions"
                    :selected-label="$this->selectedFilterZoneLabel"
                    :placeholder="__('Todas')"
                    search-placeholder="Hotel, 10"
                    :empty="__('Sin zonas')"
                    data-test="office-filter-zone"
                />

                <flux:input wire:model.live.debounce.500ms="search" :label="__('Buscar')" placeholder="Nombre, código, IATA" data-test="office-search" />

                <div class="flex items-end">
                    <flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters" class="w-full" data-test="office-clear-filters">
                        {{ __('Limpiar') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <flux:table :paginate="$this->offices">
            <flux:table.columns>
                <flux:table.column>{{ __('Oficina') }}</flux:table.column>
                <flux:table.column>{{ __('Código') }}</flux:table.column>
                <flux:table.column>{{ __('IATA') }}</flux:table.column>
                <flux:table.column>{{ __('Proveedor') }}</flux:table.column>
                <flux:table.column>{{ __('País') }}</flux:table.column>
                <flux:table.column>{{ __('Ciudad') }}</flux:table.column>
                <flux:table.column>{{ __('Zona') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->offices as $office)
                    <flux:table.row :key="$office->id">
                        <flux:table.cell variant="strong">{{ $office->name }}</flux:table.cell>
                        <flux:table.cell>{{ $office->code }}</flux:table.cell>
                        <flux:table.cell>{{ $office->iata_code ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $office->supplier?->code ?? __('Global') }}</flux:table.cell>
                        <flux:table.cell>{{ $office->zone?->city?->country?->iso2 ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $office->zone?->city?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $office->zone?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$office->status === 'active' ? 'green' : 'zinc'">
                                {{ $office->status === 'active' ? __('Activo') : __('Inactivo') }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('Aún no hay oficinas registradas.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
