<?php

use App\Models\Supplier;
use App\Models\VehicleCategory;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Categorías')] class extends Component {
    use WithPagination;

    public ?int $supplierId = null;

    public string $name = '';

    public string $code = '';

    public string $acrissPrefix = '';

    public string $description = '';

    public string $status = 'active';

    public string $search = '';

    public function mount(): void
    {
        $this->supplierId = Auth::user()->supplier_id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'supplierId' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('vehicle_categories', 'code')->where('supplier_id', $this->supplierId),
            ],
            'acrissPrefix' => ['nullable', 'string', 'max:4'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        VehicleCategory::query()->create([
            'supplier_id' => $validated['supplierId'],
            'name' => $validated['name'],
            'code' => str($validated['code'])->upper()->toString(),
            'acriss_prefix' => $validated['acrissPrefix'] !== ''
                ? str($validated['acrissPrefix'])->upper()->toString()
                : null,
            'description' => $validated['description'] ?: null,
            'status' => $validated['status'],
        ]);

        $this->reset(['name', 'code', 'acrissPrefix', 'description']);
        $this->status = 'active';
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Categoría creada.'));
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::query()
            ->select(['id', 'name', 'code'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function categories(): LengthAwarePaginator
    {
        return VehicleCategory::query()
            ->with('supplier:id,name,code')
            ->forSupplier(Auth::user()->supplier_id)
            ->when($this->search !== '', function (Builder $query): void {
                $search = str($this->search)->upper()->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('acriss_prefix', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Categorías') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Administra categorías de vehículos para agrupar tarifas, códigos ACRISS y disponibilidad por proveedor.') }}
        </flux:text>
    </div>

    <form wire:submit="save" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @if (! Auth::user()->supplier_id)
                <flux:select wire:model="supplierId" :label="__('Proveedor')" data-test="category-supplier">
                    <flux:select.option value="">{{ __('Selecciona proveedor') }}</flux:select.option>
                    @foreach ($this->suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="name" :label="__('Nombre')" placeholder="SUV" data-test="category-name" />
            <flux:input wire:model="code" :label="__('Código')" placeholder="SUV" maxlength="30" data-test="category-code" />
            <flux:input wire:model="acrissPrefix" :label="__('Prefijo ACRISS')" placeholder="IF" maxlength="4" data-test="category-acriss-prefix" />
            <flux:select wire:model="status" :label="__('Estado')" data-test="category-status">
                <flux:select.option value="active">{{ __('Activo') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactivo') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="mt-4">
            <flux:textarea wire:model="description" :label="__('Descripción')" rows="3" placeholder="Vehículos familiares de capacidad media." data-test="category-description" />
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button type="submit" variant="primary" icon="plus" data-test="category-submit">
                {{ __('Crear categoría') }}
            </flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Catálogo de categorías') }}</flux:heading>
                <flux:text>{{ __('Categorías disponibles para tarifas y reservas.') }}</flux:text>
            </div>

            <flux:input wire:model.live.debounce.300ms="search" :label="__('Buscar')" placeholder="SUV, IF, compacto" class="md:w-72" data-test="category-search" />
        </div>

        <flux:table :paginate="$this->categories">
            <flux:table.columns>
                <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                <flux:table.column>{{ __('Código') }}</flux:table.column>
                <flux:table.column>{{ __('ACRISS') }}</flux:table.column>
                <flux:table.column>{{ __('Proveedor') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Descripción') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->categories as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell variant="strong">{{ $category->name }}</flux:table.cell>
                        <flux:table.cell>{{ $category->code }}</flux:table.cell>
                        <flux:table.cell>{{ $category->acriss_prefix ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $category->supplier?->code ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$category->status === 'active' ? 'green' : 'zinc'">
                                {{ $category->status === 'active' ? __('Activo') : __('Inactivo') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $category->description ?? '-' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('Aún no hay categorías registradas.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
