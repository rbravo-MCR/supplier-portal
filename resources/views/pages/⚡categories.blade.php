<?php

use App\Models\Supplier;
use App\Models\VehicleCategory;
use App\Models\VehicleCategoryAcrissCode;
use App\Models\VehicleCategoryCatalog;
use Flux\Flux;
use Illuminate\Contracts\Pagination\Paginator;
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

    public ?int $vehicleCategoryCatalogId = null;

    public string $supplierCode = '';

    public string $acrissCode = '';

    public string $description = '';

    public string $status = 'active';

    public string $search = '';

    public ?int $editingCategoryId = null;

    public string $editSupplierCode = '';

    public string $editAcrissCode = '';

    public string $editDescription = '';

    public string $editStatus = 'active';

    public string $editCategoryLabel = '';

    public ?int $editVehicleCategoryCatalogId = null;

    public bool $showEditCategoryModal = false;

    public function mount(): void
    {
        $this->supplierId = Auth::user()->supplier_id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedVehicleCategoryCatalogId(): void
    {
        $this->acrissCode = '';
    }

    public function save(): void
    {
        $supplierId = Auth::user()->supplier_id ?: $this->supplierId;
        $acrissRules = ['nullable', 'string', 'max:4'];

        if ($this->selectedCatalog()?->acrissCodes()->exists()) {
            $acrissRules = [
                'required',
                'string',
                'max:4',
                Rule::exists('vehicle_category_acriss_codes', 'code')
                    ->where(fn ($query) => $query->where('vehicle_category_catalog_id', $this->vehicleCategoryCatalogId)),
            ];
        }

        $validated = $this->validate([
            'supplierId' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'vehicleCategoryCatalogId' => [
                'required',
                'integer',
                Rule::exists('vehicle_category_catalogs', 'id')->where('status', 'active'),
                Rule::unique('vehicle_categories', 'vehicle_category_catalog_id')->where('supplier_id', $supplierId),
            ],
            'supplierCode' => ['nullable', 'string', 'max:30'],
            'acrissCode' => $acrissRules,
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if (! $supplierId) {
            $this->addError('supplierId', __('Selecciona proveedor.'));

            return;
        }

        $catalog = VehicleCategoryCatalog::query()->findOrFail($validated['vehicleCategoryCatalogId']);

        VehicleCategory::query()->create([
            'supplier_id' => $supplierId,
            'vehicle_category_catalog_id' => $catalog->id,
            'supplier_code' => $validated['supplierCode'] !== ''
                ? str($validated['supplierCode'])->upper()->toString()
                : null,
            'name' => $catalog->name_es,
            'code' => $catalog->code,
            'acriss_prefix' => $validated['acrissCode'] !== ''
                ? str($validated['acrissCode'])->upper()->toString()
                : null,
            'description' => $validated['description'] ?: null,
            'status' => $validated['status'],
        ]);

        $this->reset(['vehicleCategoryCatalogId', 'supplierCode', 'acrissCode', 'description']);
        $this->status = 'active';
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Categoría creada.'));
    }

    public function editCategory(int $categoryId): void
    {
        abort_unless($this->canManageCategories(), 403);

        $category = $this->editableCategoryQuery()->findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->editVehicleCategoryCatalogId = $category->vehicle_category_catalog_id;
        $this->editCategoryLabel = ($category->catalog?->code ?? $category->code).' · '.($category->catalog?->name_es ?? $category->name);
        $this->editSupplierCode = $category->supplier_code ?? '';
        $this->editAcrissCode = $category->acriss_prefix ?? '';
        $this->editDescription = $category->description ?? '';
        $this->editStatus = $category->status;
        $this->resetValidation();
        $this->showEditCategoryModal = true;
    }

    public function updateCategory(): void
    {
        abort_unless($this->canManageCategories(), 403);

        $category = $this->editableCategoryQuery()->findOrFail($this->editingCategoryId);
        $acrissRules = ['nullable', 'string', 'max:4'];

        if ($category->catalog?->acrissCodes()->exists()) {
            $acrissRules = [
                'required',
                'string',
                'max:4',
                Rule::exists('vehicle_category_acriss_codes', 'code')
                    ->where(fn ($query) => $query->where('vehicle_category_catalog_id', $category->vehicle_category_catalog_id)),
            ];
        }

        $validated = $this->validate([
            'editSupplierCode' => ['nullable', 'string', 'max:30'],
            'editAcrissCode' => $acrissRules,
            'editDescription' => ['nullable', 'string', 'max:500'],
            'editStatus' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $category->update([
            'supplier_code' => $validated['editSupplierCode'] !== ''
                ? str($validated['editSupplierCode'])->upper()->toString()
                : null,
            'acriss_prefix' => $validated['editAcrissCode'] !== ''
                ? str($validated['editAcrissCode'])->upper()->toString()
                : null,
            'description' => $validated['editDescription'] ?: null,
            'status' => $validated['editStatus'],
        ]);

        $this->resetEditForm();
        $this->resetPage();
        $this->showEditCategoryModal = false;

        Flux::toast(variant: 'success', text: __('Categoría actualizada.'));
    }

    public function deleteCategory(int $categoryId): void
    {
        abort_unless($this->canManageCategories(), 403);

        $category = $this->editableCategoryQuery()->findOrFail($categoryId);

        $category->update([
            'status' => 'inactive',
        ]);

        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Categoría desactivada.'));
    }

    #[Computed]
    public function canManageCategories(): bool
    {
        return (int) Auth::user()->role_id === 4;
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

    /**
     * @return Collection<int, VehicleCategoryCatalog>
     */
    #[Computed]
    public function catalogCategories(): Collection
    {
        return VehicleCategoryCatalog::query()
            ->with('acrissCodes:id,vehicle_category_catalog_id,code')
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, VehicleCategoryAcrissCode>
     */
    #[Computed]
    public function acrissCodes(): Collection
    {
        if (! $this->vehicleCategoryCatalogId) {
            return collect();
        }

        return VehicleCategoryAcrissCode::query()
            ->where('vehicle_category_catalog_id', $this->vehicleCategoryCatalogId)
            ->orderBy('code')
            ->get(['id', 'vehicle_category_catalog_id', 'code']);
    }

    /**
     * @return Collection<int, VehicleCategoryAcrissCode>
     */
    #[Computed]
    public function editAcrissCodes(): Collection
    {
        if (! $this->editVehicleCategoryCatalogId) {
            return collect();
        }

        return VehicleCategoryAcrissCode::query()
            ->where('vehicle_category_catalog_id', $this->editVehicleCategoryCatalogId)
            ->orderBy('code')
            ->get(['id', 'vehicle_category_catalog_id', 'code']);
    }

    #[Computed]
    public function categories(): Paginator
    {
        $supplierId = Auth::user()->supplier_id;

        if (! $supplierId) {
            return new \Illuminate\Pagination\Paginator([], 10);
        }

        return VehicleCategory::query()
            ->with(['supplier:id,name,code', 'catalog:id,code,name_es'])
            ->forSupplier($supplierId)
            ->when($this->search !== '', function (Builder $query): void {
                $search = str($this->search)->upper()->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('acriss_prefix', 'like', "%{$search}%")
                        ->orWhere('supplier_code', 'like', "%{$search}%")
                        ->orWhereHas('catalog', function (Builder $query) use ($search): void {
                            $query->where('code', 'like', "%{$search}%")
                                ->orWhere('name_es', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->simplePaginate(10);
    }

    private function editableCategoryQuery(): Builder
    {
        return VehicleCategory::query()
            ->with(['catalog:id,code,name_es'])
            ->forSupplier(Auth::user()->supplier_id);
    }

    private function resetEditForm(): void
    {
        $this->reset([
            'editingCategoryId',
            'editSupplierCode',
            'editAcrissCode',
            'editDescription',
            'editCategoryLabel',
            'editVehicleCategoryCatalogId',
        ]);
        $this->editStatus = 'active';
    }

    private function selectedCatalog(): ?VehicleCategoryCatalog
    {
        if (! $this->vehicleCategoryCatalogId) {
            return null;
        }

        return VehicleCategoryCatalog::query()->find($this->vehicleCategoryCatalogId);
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

            <flux:select wire:model.live="vehicleCategoryCatalogId" :label="__('Categoría GPS')" data-test="category-catalog">
                <flux:select.option value="">{{ __('Selecciona categoría') }}</flux:select.option>
                @foreach ($this->catalogCategories as $catalogCategory)
                    <flux:select.option :value="$catalogCategory->id" wire:key="catalog-category-{{ $catalogCategory->id }}">
                        {{ $catalogCategory->code }} · {{ $catalogCategory->name_es }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model.live="acrissCode"
                wire:key="category-acriss-{{ $vehicleCategoryCatalogId ?? 'none' }}"
                :label="__('Código ACRISS')"
                :disabled="$this->acrissCodes->isEmpty()"
                data-test="category-acriss-code"
            >
                <flux:select.option value="">
                    {{ $this->acrissCodes->isEmpty() ? __('Sin códigos ACRISS') : __('Selecciona ACRISS') }}
                </flux:select.option>
                @foreach ($this->acrissCodes as $acrissCodeOption)
                    <flux:select.option :value="$acrissCodeOption->code" wire:key="category-acriss-{{ $vehicleCategoryCatalogId }}-{{ $acrissCodeOption->code }}">
                        {{ $acrissCodeOption->code }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="supplierCode" :label="__('Código proveedor')" placeholder="FULLSIZE_AUTO" maxlength="30" data-test="category-supplier-code" />
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

    @if ($this->canManageCategories)
        <flux:modal wire:model="showEditCategoryModal" class="md:w-[42rem]">
            <form wire:submit="updateCategory" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Modificar categoría') }}</flux:heading>
                    <flux:text class="mt-2">{{ $editCategoryLabel }}</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="editSupplierCode" :label="__('Código proveedor')" placeholder="FULLSIZE_AUTO" maxlength="30" data-test="category-edit-supplier-code" />

                    <flux:select
                        wire:model.live="editAcrissCode"
                        wire:key="category-edit-acriss-{{ $editVehicleCategoryCatalogId ?? 'none' }}"
                        :label="__('Código ACRISS')"
                        :disabled="$this->editAcrissCodes->isEmpty()"
                        data-test="category-edit-acriss-code"
                    >
                        <flux:select.option value="">
                            {{ $this->editAcrissCodes->isEmpty() ? __('Sin códigos ACRISS') : __('Selecciona ACRISS') }}
                        </flux:select.option>
                        @foreach ($this->editAcrissCodes as $acrissCodeOption)
                            <flux:select.option :value="$acrissCodeOption->code" wire:key="category-edit-acriss-{{ $editVehicleCategoryCatalogId }}-{{ $acrissCodeOption->code }}">
                                {{ $acrissCodeOption->code }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="editStatus" :label="__('Estado')" data-test="category-edit-status">
                        <flux:select.option value="active">{{ __('Activo') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('Inactivo') }}</flux:select.option>
                    </flux:select>
                </div>

                <flux:textarea wire:model="editDescription" :label="__('Descripción')" rows="3" placeholder="Vehículos familiares de capacidad media." data-test="category-edit-description" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">{{ __('Cancelar') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="updateCategory" data-test="category-edit-submit">
                        {{ __('Guardar cambios') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

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
                <flux:table.column>{{ __('Categoría GPS') }}</flux:table.column>
                <flux:table.column>{{ __('Código proveedor') }}</flux:table.column>
                <flux:table.column>{{ __('ACRISS') }}</flux:table.column>
                <flux:table.column>{{ __('Proveedor') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Descripción') }}</flux:table.column>
                @if ($this->canManageCategories)
                    <flux:table.column data-test="category-actions-header">{{ __('Acciones') }}</flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->categories as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell variant="strong">
                            {{ $category->catalog?->code ?? $category->code }} · {{ $category->catalog?->name_es ?? $category->name }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $category->supplier_code ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $category->acriss_prefix ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $category->supplier?->code ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$category->status === 'active' ? 'green' : 'zinc'">
                                {{ $category->status === 'active' ? __('Activo') : __('Inactivo') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $category->description ?? '-' }}</flux:table.cell>
                        @if ($this->canManageCategories)
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        wire:click="editCategory({{ $category->id }})"
                                        data-test="category-edit-{{ $category->id }}"
                                    >
                                        <span class="sr-only">{{ __('Editar') }}</span>
                                    </flux:button>

                                    <flux:button
                                        type="button"
                                        variant="danger"
                                        size="sm"
                                        icon="trash"
                                        wire:click="deleteCategory({{ $category->id }})"
                                        wire:confirm="{{ __('Esta acción desactivará la categoría. ¿Continuar?') }}"
                                        data-test="category-delete-{{ $category->id }}"
                                    >
                                        <span class="sr-only">{{ __('Borrar') }}</span>
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell :colspan="$this->canManageCategories ? 7 : 6">
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
