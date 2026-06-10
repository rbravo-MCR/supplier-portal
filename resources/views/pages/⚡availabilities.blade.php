<?php

use App\Concerns\RemembersModelRows;
use App\Models\Supplier;
use App\Models\VehicleAvailability;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Disponibilidad')] class extends Component {
    use WithPagination, RemembersModelRows;

    public string $search = '';

    public string $filterStatus = 'available';

    public string $filterDate = '';

    public string $filterAcriss = '';

    public ?int $filterSupplierId = null;

    public function mount(): void
    {
        $this->filterDate = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDate(): void
    {
        $this->resetPage();
    }

    public function updatedFilterAcriss(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSupplierId(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterAcriss', 'filterSupplierId']);
        $this->filterStatus = 'available';
        $this->filterDate = now()->toDateString();
        $this->resetPage();
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
    public function availabilities(): LengthAwarePaginator
    {
        return VehicleAvailability::query()
            ->with('supplier:id,name,code', 'office:id,name,code,iata_code')
            ->forSupplier(Auth::user()->supplier_id ?? $this->filterSupplierId)
            ->when($this->filterStatus !== 'all', fn (Builder $query) => $query->where('status', $this->filterStatus))
            ->when($this->filterDate !== '', fn (Builder $query) => $query->forDate($this->filterDate))
            ->when($this->filterAcriss !== '', fn (Builder $query) => $query->where('acriss_code', 'like', strtoupper($this->filterAcriss).'%'))
            ->when($this->search !== '', fn (Builder $query) => $query->search($this->search))
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->paginate(15);
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Disponibilidad') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Ventanas de disponibilidad de vehículos por proveedor, ubicación, clase y vigencia.') }}
        </flux:text>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3">
            <div>
                <flux:heading>{{ __('Ventanas activas') }}</flux:heading>
                <flux:text>{{ __('Filtra por fecha para ver disponibilidad en un día específico.') }}</flux:text>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @if (! Auth::user()->supplier_id)
                    <flux:select wire:model.live="filterSupplierId" :label="__('Proveedor')" data-test="avail-supplier">
                        <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                        @foreach ($this->suppliers as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:input
                    wire:model.live="filterDate"
                    :label="__('Fecha')"
                    type="date"
                    data-test="avail-date"
                />

                <flux:input
                    wire:model.live.debounce.300ms="filterAcriss"
                    :label="__('ACRISS')"
                    placeholder="ECAR"
                    maxlength="4"
                    data-test="avail-acriss"
                />

                <flux:select wire:model.live="filterStatus" :label="__('Estado')" data-test="avail-status">
                    <flux:select.option value="available">{{ __('Disponible') }}</flux:select.option>
                    <flux:select.option value="unavailable">{{ __('No disponible') }}</flux:select.option>
                    <flux:select.option value="all">{{ __('Todos') }}</flux:select.option>
                </flux:select>

                <flux:input
                    wire:model.live.debounce.500ms="search"
                    :label="__('Buscar')"
                    placeholder="CUN, SUV, ECAR"
                    data-test="avail-search"
                />

                <div class="flex items-end">
                    <flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters" class="w-full" data-test="avail-clear">
                        {{ __('Limpiar') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <flux:table :paginate="$this->availabilities">
            <flux:table.columns>
                <flux:table.column>{{ __('Vehículo') }}</flux:table.column>
                <flux:table.column>{{ __('ACRISS') }}</flux:table.column>
                <flux:table.column>{{ __('Ubicación') }}</flux:table.column>
                <flux:table.column>{{ __('Proveedor') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Disponibles') }}</flux:table.column>
                <flux:table.column>{{ __('Vigencia') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->availabilities as $availability)
                    <flux:table.row :key="$availability->id">
                        <flux:table.cell variant="strong">{{ $availability->vehicle_class }}</flux:table.cell>
                        <flux:table.cell>{{ $availability->acriss_code }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="font-mono text-xs">{{ $availability->location_code }}</span>
                            @if ($availability->iata_code)
                                <span class="ml-1 text-xs text-zinc-400">{{ $availability->iata_code }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $availability->supplier?->code ?? '-' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <span class="{{ $availability->available_quantity > 0 ? 'text-green-600 dark:text-green-400' : 'text-zinc-400' }} font-semibold tabular-nums">
                                {{ $availability->available_quantity }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="tabular-nums text-xs">
                            {{ $availability->valid_from->format('Y-m-d') }} / {{ $availability->valid_to->format('Y-m-d') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$availability->status === 'available' ? 'green' : 'zinc'">
                                {{ $availability->status === 'available' ? __('Disponible') : __('No disponible') }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('Sin ventanas de disponibilidad para los filtros seleccionados.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
