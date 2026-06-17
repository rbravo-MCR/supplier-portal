<?php

use App\Jobs\RecordAuditLog;
use App\Models\Country;
use App\Models\Supplier;
use App\Modules\Supplier\Application\DTOs\CreateSupplierData;
use App\Modules\Supplier\Application\UseCases\CreateSupplier;
use App\Modules\Supplier\Domain\Exceptions\SupplierCodeAlreadyExists;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Proveedores')] class extends Component {
    use WithPagination;

    public string $name = '';

    public string $code = '';

    public ?int $countryId = null;

    public string $integrationType = Supplier::IntegrationNone;

    public ?int $maxUsers = null;

    public string $status = 'active';

    public string $contactName = '';

    public string $email = '';

    public string $phone = '';

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Supplier::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function toggleStatus(int $supplierId): void
    {
        $supplier = Supplier::query()->findOrFail($supplierId);

        Gate::authorize('update', $supplier);

        $oldStatus = $supplier->status;
        $newStatus = $oldStatus === 'active' ? 'inactive' : 'active';

        $supplier->forceFill([
            'status' => $newStatus,
        ])->save();

        dispatch(new RecordAuditLog([
            'supplier_id' => $supplier->id,
            'user_id' => auth()->id(),
            'module' => 'supplier',
            'action' => 'status_updated',
            'entity_type' => Supplier::class,
            'entity_id' => $supplier->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus],
        ]));

        Flux::toast(variant: 'success', text: __('Estado del proveedor actualizado.'));
    }

    public function save(CreateSupplier $createSupplier): void
    {
        Gate::authorize('create', Supplier::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'code')],
            'countryId' => [
                'required',
                'integer',
                Rule::exists('countries', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->where('iso2', '<>', 'MX')),
            ],
            'integrationType' => ['required', Rule::in([Supplier::IntegrationNone])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'maxUsers' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $createSupplier->handle(
                new CreateSupplierData(
                    name: $validated['name'],
                    code: $validated['code'],
                    countryId: $validated['countryId'],
                    integrationType: $validated['integrationType'],
                    status: $validated['status'],
                    maxUsers: $validated['maxUsers'],
                    contactName: $validated['contactName'] ?: null,
                    email: $validated['email'] !== '' ? str($validated['email'])->lower()->toString() : null,
                    phone: $validated['phone'] ?: null,
                ),
                auth()->user(),
            );
        } catch (SupplierCodeAlreadyExists) {
            $this->addError('code', __('Ese código ya existe para otro proveedor.'));

            return;
        }

        $this->reset(['name', 'code', 'countryId', 'maxUsers', 'contactName', 'email', 'phone']);
        $this->integrationType = Supplier::IntegrationNone;
        $this->status = 'active';
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Proveedor creado.'));
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => __('Captura el nombre del proveedor.'),
            'code.required' => __('Captura el código del proveedor.'),
            'code.unique' => __('Ese código ya existe para otro proveedor.'),
            'countryId.required' => __('Selecciona el país fiscal del proveedor.'),
            'countryId.exists' => __('Solo se pueden dar de alta proveedores fuera de México.'),
            'integrationType.in' => __('Solo se pueden dar de alta proveedores sin API ni SOAP.'),
            'status.required' => __('Selecciona el estado del proveedor.'),
            'maxUsers.min' => __('El límite de usuarios debe ser mayor a cero.'),
        ];
    }

    #[Computed]
    public function canCreateSupplier(): bool
    {
        return Gate::allows('create', Supplier::class);
    }

    /**
     * @return Collection<int, Country>
     */
    #[Computed]
    public function countries(): Collection
    {
        return Country::query()
            ->select(['id', 'name', 'iso2'])
            ->active()
            ->where('iso2', '<>', 'MX')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function suppliers(): LengthAwarePaginator
    {
        return Supplier::query()
            ->with('country:id,name,iso2')
            ->when($this->search !== '', function (Builder $query): void {
                $query->search($this->search);
            })
            ->when($this->statusFilter !== '', function (Builder $query): void {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('name')
            ->paginate(15);
    }
};
?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Proveedores') }}</flux:heading>
            <flux:text class="max-w-3xl">
                {{ __('Mantén datos comerciales, contacto, estado y límites operativos de los proveedores disponibles para el portal.') }}
            </flux:text>
        </div>

        @if ($this->canCreateSupplier)
            <flux:modal.trigger name="create-supplier">
                <flux:button variant="primary" icon="plus" data-test="supplier-add-button">
                    {{ __('Agregar proveedor') }}
                </flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    @if ($this->canCreateSupplier)
        <flux:modal name="create-supplier" class="md:w-[42rem]">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Agregar proveedor') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Captura los datos operativos que usará el portal para vincular usuarios, oficinas y tarifas.') }}</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <flux:input wire:model="name" :label="__('Nombre comercial')" placeholder="Acme Rent a Car" data-test="supplier-name" />
                        @error('name')
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="code" :label="__('Código')" placeholder="ACME" data-test="supplier-code" />
                        @error('code')
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <flux:select wire:model="countryId" :label="__('País fiscal')" data-test="supplier-country">
                            <flux:select.option value="">{{ __('Selecciona país') }}</flux:select.option>
                            @foreach ($this->countries as $country)
                                <flux:select.option :value="$country->id">{{ $country->name }} · {{ $country->iso2 }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('countryId')
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <flux:select wire:model="integrationType" :label="__('Integración')" data-test="supplier-integration-type">
                            <flux:select.option value="none">{{ __('Sin API/SOAP') }}</flux:select.option>
                            <flux:select.option value="api">{{ __('Tiene API') }}</flux:select.option>
                            <flux:select.option value="soap">{{ __('Tiene SOAP') }}</flux:select.option>
                        </flux:select>
                        @error('integrationType')
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="maxUsers" :label="__('Límite de usuarios')" type="number" min="1" placeholder="Sin límite" data-test="supplier-max-users" />
                        @error('maxUsers')
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                        @enderror
                    </div>

                    <flux:select wire:model="status" :label="__('Estado')" data-test="supplier-status">
                        <flux:select.option value="active">{{ __('Activo') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('Inactivo') }}</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="contactName" :label="__('Contacto')" placeholder="Operaciones" data-test="supplier-contact-name" />
                    <flux:input wire:model="email" :label="__('Correo')" type="email" placeholder="ops@proveedor.com" data-test="supplier-email" />
                    <flux:input wire:model="phone" :label="__('Teléfono')" placeholder="+52 555 0100" data-test="supplier-phone" />
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancelar') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary" icon="building-office-2" wire:loading.attr="disabled" wire:target="save" data-test="supplier-submit">
                        <span wire:loading.remove wire:target="save">{{ __('Crear proveedor') }}</span>
                        <span wire:loading wire:target="save">{{ __('Creando...') }}</span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Directorio de proveedores') }}</flux:heading>
                <flux:text>{{ __('Proveedores registrados para administración operativa.') }}</flux:text>
            </div>

            <div class="grid gap-3 md:grid-cols-[12rem_20rem_auto]">
                <flux:select wire:model.live="statusFilter" :label="__('Estado')" data-test="supplier-status-filter">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('Activos') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactivos') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model.live.debounce.500ms="search" :label="__('Buscar')" placeholder="Nombre, código o contacto" data-test="supplier-search" />

                <div class="flex items-end">
                    <flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters" class="w-full" data-test="supplier-clear-filters">
                        {{ __('Limpiar') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <flux:table :paginate="$this->suppliers">
            <flux:table.columns>
                <flux:table.column>{{ __('Proveedor') }}</flux:table.column>
                <flux:table.column>{{ __('Código') }}</flux:table.column>
                <flux:table.column>{{ __('País') }}</flux:table.column>
                <flux:table.column>{{ __('Integración') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Límite') }}</flux:table.column>
                <flux:table.column>{{ __('Contacto') }}</flux:table.column>
                <flux:table.column>{{ __('Correo') }}</flux:table.column>
                <flux:table.column>{{ __('Teléfono') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->suppliers as $supplier)
                    <flux:table.row :key="$supplier->id">
                        <flux:table.cell variant="strong">{{ $supplier->name }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->code }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->country ? "{$supplier->country->name} · {$supplier->country->iso2}" : '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->integration_type === 'none' ? __('Sin API/SOAP') : str($supplier->integration_type)->upper() }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex min-w-36 items-center gap-3">
                                @can('update', $supplier)
                                    @if ($supplier->status === 'active')
                                        <button
                                            type="button"
                                            wire:click="toggleStatus({{ $supplier->id }})"
                                            wire:target="toggleStatus({{ $supplier->id }})"
                                            wire:loading.attr="disabled"
                                            class="group inline-flex min-w-32 items-center justify-between gap-3 rounded-full border border-emerald-700 bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60 dark:border-emerald-300 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400"
                                            data-test="supplier-status-toggle-{{ $supplier->id }}"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                <span class="size-2 rounded-full bg-white dark:bg-emerald-950"></span>
                                                {{ __('Activo') }}
                                            </span>

                                            <span class="rounded-full bg-white px-2 py-0.5 text-[11px] text-emerald-800 shadow-sm ring-1 ring-white/80 dark:bg-emerald-950 dark:text-emerald-100 dark:ring-emerald-950">
                                                {{ __('Pausar') }}
                                            </span>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="toggleStatus({{ $supplier->id }})"
                                            wire:target="toggleStatus({{ $supplier->id }})"
                                            wire:loading.attr="disabled"
                                            class="group inline-flex min-w-32 items-center justify-between gap-3 rounded-full border border-zinc-300 bg-zinc-200 px-2.5 py-1.5 text-xs font-semibold text-zinc-800 transition hover:bg-zinc-300 disabled:cursor-wait disabled:opacity-60 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-600"
                                            data-test="supplier-status-toggle-{{ $supplier->id }}"
                                        >
                                            <span class="inline-flex items-center gap-2">
                                                <span class="size-2 rounded-full bg-zinc-400"></span>
                                                {{ __('Inactivo') }}
                                            </span>

                                            <span class="rounded-full bg-zinc-50 px-2 py-0.5 text-[11px] text-zinc-800 shadow-sm ring-1 ring-zinc-300 group-hover:bg-white dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-600 dark:group-hover:bg-zinc-950">
                                                {{ __('Activar') }}
                                            </span>
                                        </button>
                                    @endif
                                @else
                                    <flux:badge :color="$supplier->status === 'active' ? 'green' : 'zinc'">
                                        {{ $supplier->status === 'active' ? __('Activo') : __('Inactivo') }}
                                    </flux:badge>
                                @endcan
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $supplier->max_users ?? __('Sin límite') }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->contact_name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->email ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->phone ?? '-' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('No hay proveedores para los filtros seleccionados.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
