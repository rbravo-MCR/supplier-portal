<?php

use App\Concerns\RemembersModelRows;
use App\Models\Rate;
use App\Models\Supplier;
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

    public string $vehicleClass = '';

    public string $acrissCode = '';

    public string $ratePlanCode = '';

    public string $currency = 'USD';

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

    public function save(): void
    {
        $validated = $this->validate([
            'supplierId' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'officeCode' => ['required', 'string', 'max:10'],
            'vehicleClass' => ['required', 'string', 'max:50'],
            'acrissCode' => ['required', 'string', 'size:4'],
            'ratePlanCode' => ['required', 'string', 'max:30'],
            'currency' => ['required', 'string', 'size:3'],
            'basePrice' => ['required', 'numeric', 'min:0.01'],
            'validFrom' => ['required', 'date'],
            'validTo' => ['required', 'date', 'after_or_equal:validFrom'],
            'minDays' => ['nullable', 'integer', 'min:1'],
            'maxDays' => ['nullable', 'integer', 'gte:minDays'],
        ]);

        $officeCode = str($validated['officeCode'])->upper()->toString();
        $acrissCode = str($validated['acrissCode'])->upper()->toString();
        $ratePlanCode = str($validated['ratePlanCode'])->upper()->toString();

        Rate::query()->create([
            'supplier_id' => $validated['supplierId'],
            'office_code' => $officeCode,
            'vehicle_class' => str($validated['vehicleClass'])->upper()->toString(),
            'acriss_code' => $acrissCode,
            'rate_plan_code' => $ratePlanCode,
            'currency' => str($validated['currency'])->upper()->toString(),
            'base_price' => $validated['basePrice'],
            'valid_from' => $validated['validFrom'],
            'valid_to' => $validated['validTo'],
            'min_days' => $validated['minDays'],
            'max_days' => $validated['maxDays'],
            'status' => 'active',
            'version' => $this->nextVersion($validated),
            'created_by' => Auth::id(),
        ]);

        Cache::forget("rate.version.{$validated['supplierId']}.{$officeCode}.{$acrissCode}.{$ratePlanCode}");
        Cache::forget("rate.overlap.{$validated['supplierId']}.{$officeCode}.{$acrissCode}.{$ratePlanCode}.{$validated['validFrom']}.{$validated['validTo']}");

        $this->reset([
            'officeCode',
            'vehicleClass',
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
     * @param array<string, mixed> $validated
     */
    protected function nextVersion(array $validated): int
    {
        $supplierId = $validated['supplierId'];
        $officeCode = str($validated['officeCode'])->upper()->toString();
        $acrissCode = str($validated['acrissCode'])->upper()->toString();
        $ratePlanCode = str($validated['ratePlanCode'])->upper()->toString();
        $cacheKey = "rate.version.{$supplierId}.{$officeCode}.{$acrissCode}.{$ratePlanCode}";

        return Cache::remember($cacheKey, 300, function () use ($supplierId, $officeCode, $acrissCode, $ratePlanCode): int {
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
            ->with('supplier:id,name,code')
            ->forSupplier(Auth::user()->supplier_id)
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->search !== '', function (Builder $query): void {
                $query->search($this->search);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->paginate(10);
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Precios') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Publica tarifas y consulta los datos de vehículos disponibles por clase, código ACRISS, oficina y vigencia.') }}
        </flux:text>
    </div>

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @if (! Auth::user()->supplier_id)
                <flux:select wire:model="supplierId" :label="__('Proveedor')" data-test="price-supplier">
                    <flux:select.option value="">{{ __('Selecciona proveedor') }}</flux:select.option>
                    @foreach ($this->suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="officeCode" :label="__('Oficina')" placeholder="CUN" maxlength="10" data-test="price-office" />
            <flux:input wire:model="vehicleClass" :label="__('Clase vehículo')" placeholder="SUV" maxlength="50" data-test="price-vehicle-class" />
            <flux:input wire:model="acrissCode" :label="__('ACRISS')" placeholder="IFAR" maxlength="4" data-test="price-acriss" />
            <flux:input wire:model="ratePlanCode" :label="__('Plan')" placeholder="STD" maxlength="30" data-test="price-plan" />
            <flux:input wire:model="currency" :label="__('Moneda')" placeholder="USD" maxlength="3" data-test="price-currency" />
            <flux:input wire:model="basePrice" :label="__('Precio base')" type="number" step="0.01" min="0.01" data-test="price-base" />
            <flux:input wire:model="validFrom" :label="__('Vigente desde')" type="date" data-test="price-valid-from" />
            <flux:input wire:model="validTo" :label="__('Vigente hasta')" type="date" data-test="price-valid-to" />
            <flux:input wire:model="minDays" :label="__('Mín. días')" type="number" min="1" data-test="price-min-days" />
            <flux:input wire:model="maxDays" :label="__('Máx. días')" type="number" min="1" data-test="price-max-days" />
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button type="submit" variant="primary" icon="plus" data-test="price-submit">
                {{ __('Publicar tarifa') }}
            </flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Vehículos y tarifas') }}</flux:heading>
                <flux:text>{{ __('Datos derivados de las tarifas publicadas.') }}</flux:text>
            </div>

            <div class="grid gap-3 md:grid-cols-[16rem_10rem]">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Buscar')" placeholder="SUV, IFAR, CUN" data-test="price-search" />
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
                        <flux:table.cell>{{ $rate->valid_from->format('Y-m-d') }} / {{ $rate->valid_to->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $rate->currency }} {{ number_format((float) $rate->base_price, 2) }}</flux:table.cell>
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
