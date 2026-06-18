<?php

use App\Concerns\RemembersModelRows;
use App\Models\Currency;
use App\Models\Office;
use App\Models\Rate;
use App\Models\Supplier;
use App\Models\VehicleCategory;
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

new #[Title('Precios')] class extends Component {
    use WithPagination, RemembersModelRows;

    public ?int $supplierId = null;

    public string $officeCode = '';

    public ?int $vehicleCategoryId = null;

    public string $acrissCode = '';

    public string $ratePlanCode = '';

    public ?int $currencyId = null;

    public string $basePrice = '';

    public string $validFrom = '';

    public string $validTo = '';

    public ?int $minDays = null;

    public ?int $maxDays = null;

    public string $search = '';

    public string $status = 'active';

    public function mount(): void
    {
        $this->supplierId = Auth::user()->supplier_id;
        $this->currencyId = $this->defaultCurrencyIdForSupplier($this->supplierId);
        $this->validFrom = now()->toDateString();
        $this->validTo = now()->addDays(30)->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierId(): void
    {
        $this->reset(['officeCode', 'vehicleCategoryId', 'acrissCode']);
        $this->currencyId = $this->defaultCurrencyIdForSupplier($this->supplierId);
    }

    public function updatedVehicleCategoryId(): void
    {
        $this->acrissCode = '';
    }

    public function save(): void
    {
        $supplierId = Auth::user()->supplier_id ?: $this->supplierId;

        if (!$supplierId) {
            $this->addError('supplierId', __('Selecciona proveedor.'));

            return;
        }

        $validated = $this->validate([
            'supplierId' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'officeCode' => [
                'required',
                'string',
                'max:10',
                Rule::exists('offices', 'code')
                    ->where('supplier_id', $supplierId)
                    ->where('status', 'active'),
            ],
            'vehicleCategoryId' => [
                'required',
                'integer',
                Rule::exists('vehicle_categories', 'id')
                    ->where('supplier_id', $supplierId)
                    ->where('status', 'active'),
            ],
            'acrissCode' => ['required', 'string', 'size:4'],
            'ratePlanCode' => ['required', 'string', 'max:30'],
            'currencyId' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'basePrice' => ['required', 'numeric', 'min:0.01'],
            'validFrom' => ['required', 'date'],
            'validTo' => ['required', 'date', 'after_or_equal:validFrom'],
            'minDays' => ['nullable', 'integer', 'min:1'],
            'maxDays' => ['nullable', 'integer', 'gte:minDays'],
        ]);

        $vehicleCategory = $this->selectedVehicleCategory($supplierId);

        if (!$vehicleCategory) {
            $this->addError('vehicleCategoryId', __('Selecciona una categoría válida para el proveedor.'));

            return;
        }

        $officeCode = str($validated['officeCode'])->upper()->toString();
        $acrissCode = str($validated['acrissCode'])->upper()->toString();
        $ratePlanCode = str($validated['ratePlanCode'])->upper()->toString();
        $currency = Currency::query()->findOrFail($validated['currencyId']);
        $allowedAcrissCodes = $this->allowedAcrissCodes($vehicleCategory);

        if ($currency->decimal_places === 0 && (float) $validated['basePrice'] !== floor((float) $validated['basePrice'])) {
            $this->addError('basePrice', __('La moneda seleccionada no permite decimales.'));

            return;
        }

        if (!in_array($acrissCode, $allowedAcrissCodes, true)) {
            $this->addError('acrissCode', __('Selecciona un código ACRISS válido para la categoría.'));

            return;
        }

        Rate::query()->create([
            'supplier_id' => $supplierId,
            'office_code' => $officeCode,
            'vehicle_class' => $vehicleCategory->catalog?->code ?? $vehicleCategory->code,
            'acriss_code' => $acrissCode,
            'rate_plan_code' => $ratePlanCode,
            'currency_id' => $currency->id,
            'base_price' => $validated['basePrice'],
            'valid_from' => $validated['validFrom'],
            'valid_to' => $validated['validTo'],
            'min_days' => $validated['minDays'],
            'max_days' => $validated['maxDays'],
            'status' => 'active',
            'version' => $this->nextVersion([...$validated, 'supplierId' => $supplierId]),
            'created_by' => Auth::id(),
        ]);

        Cache::forget("rate.version.{$supplierId}.{$officeCode}.{$acrissCode}.{$ratePlanCode}");
        Cache::forget("rate.overlap.{$supplierId}.{$officeCode}.{$acrissCode}.{$ratePlanCode}.{$validated['validFrom']}.{$validated['validTo']}");

        $this->reset([
            'officeCode',
            'vehicleCategoryId',
            'acrissCode',
            'ratePlanCode',
            'basePrice',
            'minDays',
            'maxDays',
        ]);

        $this->validFrom = now()->toDateString();
        $this->validTo = now()->addDays(30)->toDateString();
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Tarifa publicada.'));
    }

    /**
     * @return Collection<int, Currency>
     */
    #[Computed]
    public function currencies(): Collection
    {
        return $this->rememberModels('currencies.active', Currency::class, function () {
            return Currency::query()
                ->select(['id', 'code', 'name', 'symbol', 'decimal_places'])
                ->active()
                ->orderBy('code')
                ->get();
        });
    }

    /**
     * @return Collection<int, Office>
     */
    #[Computed]
    public function offices(): Collection
    {
        $supplierId = Auth::user()->supplier_id ?: $this->supplierId;

        if (!$supplierId) {
            return collect();
        }

        return $this->rememberModels("offices.active.{$supplierId}", Office::class, function () use ($supplierId) {
            return Office::query()
                ->select(['id', 'supplier_id', 'name', 'code', 'iata_code', 'status'])
                ->where('supplier_id', $supplierId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * @return Collection<int, VehicleCategory>
     */
    #[Computed]
    public function vehicleCategories(): Collection
    {
        $supplierId = Auth::user()->supplier_id ?: $this->supplierId;

        if (!$supplierId) {
            return collect();
        }

        return VehicleCategory::query()
            ->with(['catalog:id,code,name_es', 'catalog.acrissCodes:id,vehicle_category_catalog_id,code'])
            ->select(['id', 'supplier_id', 'vehicle_category_catalog_id', 'supplier_code', 'name', 'code', 'acriss_prefix', 'status'])
            ->where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function acrissCodes(): Collection
    {
        $supplierId = Auth::user()->supplier_id ?: $this->supplierId;

        if (!$supplierId || !$this->vehicleCategoryId) {
            return collect();
        }

        $cacheKey = "acriss_codes.{$this->vehicleCategoryId}";
        $cachedCodes = Cache::get($cacheKey);

        if (!is_array($cachedCodes)) {
            Cache::forget($cacheKey);

            $vehicleCategory = $this->selectedVehicleCategory($supplierId);

            if (!$vehicleCategory) {
                return collect();
            }

            $cachedCodes = collect($this->allowedAcrissCodes($vehicleCategory))
                ->sort()
                ->values()
                ->all();

            Cache::put($cacheKey, $cachedCodes, 3600);
        }

        return collect($cachedCodes)
            ->filter(fn(mixed $code): bool => is_string($code))
            ->values();
    }

    /**
     * @param array<string, mixed> $validated
     */
    protected function nextVersion(array $validated): int
    {
        $supplierId = $validated['supplierId'];
        $officeCode = str($validated['officeCode'])->upper()->toString();
        $acrissCode = str($validated['acrissCode'])->upper()->toString();
        $ratePlanCode = str($validated['ratePlanCode'])->upper()->toString();
        $cacheKey = "rate.version.{$supplierId}.{$officeCode}.{$acrissCode}.{$ratePlanCode}";

        return Cache::remember($cacheKey, 3600, function () use ($supplierId, $officeCode, $acrissCode, $ratePlanCode): int {
            $latestVersion = Rate::query()
                ->where('supplier_id', $supplierId)
                ->where('office_code', $officeCode)
                ->where('acriss_code', $acrissCode)
                ->where('rate_plan_code', $ratePlanCode)
                ->max('version');

            return ((int) $latestVersion) + 1;
        });
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

    #[Computed]
    public function rates(): LengthAwarePaginator
    {
        return Rate::query()
            ->with(['supplier:id,name,code', 'currency:id,code,symbol,decimal_places'])
            ->forSupplier(Auth::user()->supplier_id)
            ->when($this->status !== 'all', fn(Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', function (Builder $query): void {
                $query->search($this->search);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->paginate(10);
    }

    private function selectedVehicleCategory(?int $supplierId): ?VehicleCategory
    {
        if (!$supplierId || !$this->vehicleCategoryId) {
            return null;
        }

        return VehicleCategory::query()
            ->with(['catalog:id,code,name_es', 'catalog.acrissCodes:id,vehicle_category_catalog_id,code'])
            ->whereKey($this->vehicleCategoryId)
            ->where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->first();
    }

    private function defaultCurrencyIdForSupplier(?int $supplierId): ?int
    {
        if ($supplierId === null) {
            return null;
        }

        return Supplier::query()
            ->whereKey($supplierId)
            ->join('countries', 'suppliers.country_id', '=', 'countries.id')
            ->whereNotNull('countries.currency_id')
            ->value('countries.currency_id');
    }

    /**
     * @return list<string>
     */
    private function allowedAcrissCodes(VehicleCategory $vehicleCategory): array
    {
        $catalogCodes = $vehicleCategory->catalog?->acrissCodes
            ->pluck('code')
            ->map(fn(string $code): string => str($code)->upper()->toString())
            ->all() ?? [];

        if ($catalogCodes !== []) {
            return array_values(array_unique($catalogCodes));
        }

        if ($vehicleCategory->acriss_prefix) {
            return [str($vehicleCategory->acriss_prefix)->upper()->toString()];
        }

        return [];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Precios') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Publica tarifas y consulta los datos de vehículos disponibles por clase, código ACRISS, oficina y vigencia.') }}
        </flux:text>
    </div>

    <form wire:submit="save"
        class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @if (!Auth::user()->supplier_id)
                <flux:select wire:model="supplierId" :label="__('Proveedor')" data-test="price-supplier">
                    <flux:select.option value="">{{ __('Selecciona proveedor') }}</flux:select.option>
                    @foreach ($this->suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="officeCode" :label="__('Oficina')" data-test="price-office">
                <flux:select.option value="">
                    {{ $this->offices->isEmpty() ? __('Sin oficinas del proveedor') : __('Selecciona oficina') }}
                </flux:select.option>
                @foreach ($this->offices as $office)
                    <flux:select.option :value="$office->code" wire:key="price-office-{{ $office->id }}">
                        {{ $office->name }} · {{ $office->code }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="vehicleCategoryId" :label="__('Categoría vehículo')"
                data-test="price-vehicle-category">
                <flux:select.option value="">
                    {{ $this->vehicleCategories->isEmpty() ? __('Sin categorías del proveedor') : __('Selecciona categoría') }}
                </flux:select.option>
                @foreach ($this->vehicleCategories as $vehicleCategory)
                    <flux:select.option :value="$vehicleCategory->id"
                        wire:key="price-vehicle-category-{{ $vehicleCategory->id }}">
                        {{ $vehicleCategory->catalog?->code ?? $vehicleCategory->code }} ·
                        {{ $vehicleCategory->catalog?->name_es ?? $vehicleCategory->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="acrissCode" wire:key="price-acriss-{{ $vehicleCategoryId ?? 'none' }}"
                :label="__('ACRISS')" :disabled="$this->acrissCodes->isEmpty()" data-test="price-acriss">
                <flux:select.option value="">
                    {{ $this->acrissCodes->isEmpty() ? __('Sin códigos ACRISS') : __('Selecciona ACRISS') }}
                </flux:select.option>
                @foreach ($this->acrissCodes as $acrissCodeOption)
                    <flux:select.option :value="$acrissCodeOption"
                        wire:key="price-acriss-{{ $vehicleCategoryId }}-{{ $acrissCodeOption }}">
                        {{ $acrissCodeOption }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="ratePlanCode" :label="__('Plan')" placeholder="STD" maxlength="30"
                data-test="price-plan" />
            <flux:select wire:model="currencyId" :label="__('Moneda')" data-test="price-currency">
                <flux:select.option value="">{{ __('Selecciona moneda') }}</flux:select.option>
                @foreach ($this->currencies as $currency)
                    <flux:select.option :value="$currency->id" wire:key="price-currency-{{ $currency->id }}">
                        {{ $currency->code }} · {{ $currency->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="basePrice" :label="__('Precio base')" type="number" step="0.01" min="0.01"
                data-test="price-base" />
            <flux:input wire:model="validFrom" :label="__('Vigente desde')" type="date" data-test="price-valid-from" />
            <flux:input wire:model="validTo" :label="__('Vigente hasta')" type="date" data-test="price-valid-to" />
            <flux:input wire:model="minDays" :label="__('Mín. días')" type="number" min="1"
                data-test="price-min-days" />
            <flux:input wire:model="maxDays" :label="__('Máx. días')" type="number" min="1"
                data-test="price-max-days" />
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button type="submit" variant="primary" icon="plus" data-test="price-submit">
                {{ __('Publicar tarifa') }}
            </flux:button>
        </div>
    </form>

    <div
        class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Vehículos y tarifas') }}</flux:heading>
                <flux:text>{{ __('Datos derivados de las tarifas publicadas.') }}</flux:text>
            </div>

            <div class="grid gap-3 md:grid-cols-[16rem_10rem]">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Buscar')" placeholder="SUV, IFAR, CUN"
                    data-test="price-search" />
                <flux:select wire:model.live="status" :label="__('Estado')" data-test="price-status">
                    <flux:select.option value="active">{{ __('Activo') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactivo') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('Todos') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        <flux:table :paginate="$this->rates">
            <flux:table.columns>
                <flux:table.column>{{ __('Vehículo') }}</flux:table.column>
                <flux:table.column>{{ __('ACRISS') }}</flux:table.column>
                <flux:table.column>{{ __('Oficina') }}</flux:table.column>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Proveedor') }}</flux:table.column>
                <flux:table.column>{{ __('Vigencia') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Precio') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->rates as $rate)
                    <flux:table.row :key="$rate->id">
                        <flux:table.cell variant="strong">{{ $rate->vehicle_class }}</flux:table.cell>
                        <flux:table.cell>{{ $rate->acriss_code }}</flux:table.cell>
                        <flux:table.cell>{{ $rate->office_code }}</flux:table.cell>
                        <flux:table.cell>{{ $rate->rate_plan_code }}</flux:table.cell>
                        <flux:table.cell>{{ $rate->supplier?->code ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ format_date($rate->valid_from) }} / {{ format_date($rate->valid_to) }}
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            {{ format_money($rate->base_price, $rate->currency?->code, $rate->currency?->decimal_places ?? 2) }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('Aún no hay vehículos/tarifas para mostrar.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
