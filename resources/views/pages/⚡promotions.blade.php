<?php

use App\Concerns\RemembersModelRows;
use App\Models\Office;
use App\Models\Promotion;
use App\Models\VehicleCategory;
use App\Modules\Promotions\Application\DTOs\CreatePromotionData;
use App\Modules\Promotions\Application\DTOs\ListPromotionsFilter;
use App\Modules\Promotions\Application\DTOs\UpdatePromotionData;
use App\Modules\Promotions\Application\UseCases\CreatePromotion;
use App\Modules\Promotions\Application\UseCases\DeletePromotion;
use App\Modules\Promotions\Application\UseCases\ListPromotions;
use App\Modules\Promotions\Application\UseCases\TogglePromotionStatus;
use App\Modules\Promotions\Application\UseCases\UpdatePromotion;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Promociones')] class extends Component {
    use WithPagination, RemembersModelRows;

    public ?string $editingUuid = null;

    public string $name = '';

    public string $type = 'seasonal';

    public string $discountType = 'percentage';

    public string $discountValue = '';

    public string $validFrom = '';

    public string $validTo = '';

    public bool $appliesToAllOffices = false;

    public bool $appliesToAllCategories = false;

    public array $selectedOfficeIds = [];

    public array $selectedCategoryIds = [];

    public array $tiers = [];

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public function mount(): void
    {
        $this->validFrom = now()->toDateString();
        $this->validTo = now()->addDays(30)->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        if ($this->type === 'volume' && empty($this->tiers)) {
            $this->tiers = [['min_days' => 7, 'max_days' => 13, 'discount_value' => 10]];
        }
    }

    public function updatedAppliesToAllOffices(): void
    {
        if ($this->appliesToAllOffices) {
            $this->selectedOfficeIds = [];
        }
    }

    public function toggleOffice(int $officeId): void
    {
        if (in_array($officeId, $this->selectedOfficeIds, true)) {
            $this->selectedOfficeIds = array_values(array_filter(
                $this->selectedOfficeIds,
                fn (int $id) => $id !== $officeId,
            ));
        } else {
            $this->selectedOfficeIds[] = $officeId;
        }
    }

    public function updatedAppliesToAllCategories(): void
    {
        if ($this->appliesToAllCategories) {
            $this->selectedCategoryIds = [];
        }
    }

    public function toggleCategory(int $categoryId): void
    {
        if (in_array($categoryId, $this->selectedCategoryIds, true)) {
            $this->selectedCategoryIds = array_values(array_filter(
                $this->selectedCategoryIds,
                fn (int $id) => $id !== $categoryId,
            ));
        } else {
            $this->selectedCategoryIds[] = $categoryId;
        }
    }

    public function addTier(): void
    {
        $this->tiers[] = ['min_days' => '', 'max_days' => '', 'discount_value' => ''];
    }

    public function removeTier(int $index): void
    {
        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['seasonal', 'volume'])],
            'discountType' => ['required', Rule::in(['percentage', 'fixed_amount'])],
            'discountValue' => ['required', 'numeric', 'min:0.01'],
            'validFrom' => ['required', 'date'],
            'validTo' => ['required', 'date', 'after_or_equal:validFrom'],
            'appliesToAllOffices' => ['boolean'],
            'appliesToAllCategories' => ['boolean'],
            'selectedOfficeIds' => ['array'],
            'selectedOfficeIds.*' => ['integer', Rule::exists('offices', 'id')->where('supplier_id', Auth::user()->supplier_id)],
            'selectedCategoryIds' => ['array'],
            'selectedCategoryIds.*' => ['integer', Rule::exists('vehicle_categories', 'id')->where('supplier_id', Auth::user()->supplier_id)],
        ];

        if ($this->type === 'volume') {
            $rules['tiers'] = ['required', 'array', 'min:1'];
            $rules['tiers.*.min_days'] = ['required', 'integer', 'min:1'];
            $rules['tiers.*.max_days'] = ['nullable', 'integer', 'min:1'];
            $rules['tiers.*.discount_value'] = ['required', 'numeric', 'min:0.01'];
        }

        $validated = $this->validate($rules);

        $supplierId = Auth::user()->supplier_id;

        if ($this->editingUuid) {
            $promotion = Promotion::query()->where('uuid', $this->editingUuid)->firstOrFail();
            $this->authorize('update', $promotion);

            app(UpdatePromotion::class)->handle($promotion, new UpdatePromotionData(
                name: $validated['name'],
                discountType: $validated['discountType'],
                discountValue: (float) $validated['discountValue'],
                validFrom: $validated['validFrom'],
                validTo: $validated['validTo'],
                appliesToAllOffices: $validated['appliesToAllOffices'],
                appliesToAllCategories: $validated['appliesToAllCategories'],
                officeIds: $validated['appliesToAllOffices'] ? [] : $validated['selectedOfficeIds'],
                categoryIds: $validated['appliesToAllCategories'] ? [] : $validated['selectedCategoryIds'],
                tiers: $validated['type'] === 'volume' ? $this->tiers : null,
            ));

            Flux::toast(variant: 'success', text: __('Promoción actualizada.'));
        } else {
            $this->authorize('create', Promotion::class);

            app(CreatePromotion::class)->handle(new CreatePromotionData(
                name: $validated['name'],
                type: $validated['type'],
                discountType: $validated['discountType'],
                discountValue: (float) $validated['discountValue'],
                validFrom: $validated['validFrom'],
                validTo: $validated['validTo'],
                appliesToAllOffices: $validated['appliesToAllOffices'],
                appliesToAllCategories: $validated['appliesToAllCategories'],
                officeIds: $validated['appliesToAllOffices'] ? [] : $validated['selectedOfficeIds'],
                categoryIds: $validated['appliesToAllCategories'] ? [] : $validated['selectedCategoryIds'],
                tiers: $validated['type'] === 'volume' ? $this->tiers : [],
                createdBy: Auth::id(),
            ));

            Flux::toast(variant: 'success', text: __('Promoción creada.'));
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function edit(string $uuid): void
    {
        $promotion = Promotion::query()
            ->with(['offices:id', 'vehicleCategories:id', 'tiers'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('update', $promotion);

        $this->editingUuid = $promotion->uuid;
        $this->name = $promotion->name;
        $this->type = $promotion->type;
        $this->discountType = $promotion->discount_type;
        $this->discountValue = (string) $promotion->discount_value;
        $this->validFrom = $promotion->valid_from->toDateString();
        $this->validTo = $promotion->valid_to->toDateString();
        $this->appliesToAllOffices = $promotion->applies_to_all_offices;
        $this->appliesToAllCategories = $promotion->applies_to_all_categories;
        $this->selectedOfficeIds = $promotion->offices->pluck('id')->toArray();
        $this->selectedCategoryIds = $promotion->vehicleCategories->pluck('id')->toArray();
        $this->tiers = $promotion->tiers->map(fn ($t) => [
            'min_days' => $t->min_days,
            'max_days' => $t->max_days,
            'discount_value' => (string) $t->discount_value,
        ])->toArray();
    }

    public function delete(string $uuid): void
    {
        $promotion = Promotion::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $promotion);

        app(DeletePromotion::class)->handle($promotion);

        $this->resetPage();
        Flux::toast(variant: 'success', text: __('Promoción eliminada.'));
    }

    public function toggleStatus(string $uuid): void
    {
        $promotion = Promotion::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $promotion);

        app(TogglePromotionStatus::class)->handle($promotion);

        Flux::toast(variant: 'success', text: $promotion->fresh()->status === 'active'
            ? __('Promoción activada.')
            : __('Promoción desactivada.'));
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingUuid', 'name', 'type', 'discountType', 'discountValue',
            'appliesToAllOffices', 'appliesToAllCategories',
            'selectedOfficeIds', 'selectedCategoryIds', 'tiers',
        ]);
        $this->validFrom = now()->toDateString();
        $this->validTo = now()->addDays(30)->toDateString();
        $this->discountType = 'percentage';
        $this->type = 'seasonal';
        $this->appliesToAllOffices = false;
        $this->appliesToAllCategories = false;
    }

    #[Computed]
    public function promotions(): LengthAwarePaginator
    {
        $supplierId = Auth::user()->supplier_id;

        return app(ListPromotions::class)->handle(
            $supplierId,
            new ListPromotionsFilter(
                type: $this->typeFilter ?: null,
                status: $this->statusFilter ?: null,
                search: $this->search ?: null,
                perPage: 10,
            )
        );
    }

    /**
     * @return Collection<int, Office>
     */
    #[Computed]
    public function offices(): Collection
    {
        return Office::query()
            ->select(['id', 'name', 'code'])
            ->forSupplier(Auth::user()->supplier_id)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, VehicleCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return VehicleCategory::query()
            ->select(['id', 'name', 'code'])
            ->forSupplier(Auth::user()->supplier_id)
            ->orderBy('name')
            ->get();
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Promociones') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Gestiona promociones por temporada y descuentos por volumen para tus tarifas.') }}
        </flux:text>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading>{{ $editingUuid ? __('Editar promoción') : __('Nueva promoción') }}</flux:heading>

        <form wire:submit="save" class="flex flex-col gap-6">
            <!-- Sección: General -->
            <div>
                <flux:heading size="sm" class="mb-4">{{ __('Información General') }}</flux:heading>
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Nombre de la promoción')" :placeholder="__('Ej: Verano 2026')" required data-test="promotion-name" />
                    <flux:select wire:model.live="type" :label="__('Tipo de promoción')" required data-test="promotion-type">
                        <flux:select.option value="seasonal">{{ __('Temporada') }}</flux:select.option>
                        <flux:select.option value="volume">{{ __('Volumen') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <flux:separator variant="subtle" />

            <!-- Sección: Descuento y Vigencia -->
            <div>
                <flux:heading size="sm" class="mb-4">{{ __('Descuento y Vigencia') }}</flux:heading>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <flux:select wire:model="discountType" :label="__('Tipo de descuento')" required data-test="promotion-discount-type">
                        <flux:select.option value="percentage">{{ __('Porcentaje') }}</flux:select.option>
                        <flux:select.option value="fixed_amount">{{ __('Monto fijo') }}</flux:select.option>
                    </flux:select>
                    
                    <flux:input wire:model="discountValue" :label="__('Valor del descuento')" type="number" step="0.01" min="0.01" required data-test="promotion-discount-value" />
                    
                    <flux:input wire:model="validFrom" :label="__('Vigente desde')" type="date" required data-test="promotion-valid-from" />
                    
                    <flux:input wire:model="validTo" :label="__('Vigente hasta')" type="date" required data-test="promotion-valid-to" />
                </div>
            </div>

            <!-- Sección: Niveles (Sólo si es por volumen) -->
            @if ($type === 'volume')
                <flux:separator variant="subtle" />
                <div>
                    <flux:heading size="sm" class="mb-4">{{ __('Niveles de descuento por días') }}</flux:heading>
                    <div class="flex flex-col gap-3">
                        @foreach ($tiers as $index => $tier)
                            <div class="flex items-end gap-3">
                                <flux:input wire:model="tiers.{{ $index }}.min_days" :label="__('Mín. días')" type="number" min="1" required class="w-24" />
                                <flux:input wire:model="tiers.{{ $index }}.max_days" :label="__('Máx. días')" type="number" min="1" placeholder="∞" class="w-24" />
                                <flux:input wire:model="tiers.{{ $index }}.discount_value" :label="__('% Desc.')" type="number" step="0.01" min="0.01" required class="w-28" />
                                <flux:button type="button" variant="danger" icon="trash" wire:click="removeTier({{ $index }})" class="mb-0.5" data-test="promotion-remove-tier-{{ $index }}">
                                    <span class="sr-only">{{ __('Eliminar') }}</span>
                                </flux:button>
                            </div>
                        @endforeach
                        <div>
                            <flux:button type="button" variant="ghost" size="sm" icon="plus" wire:click="addTier" data-test="promotion-add-tier">
                                {{ __('Agregar nivel') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            <flux:separator variant="subtle" />

            <!-- Sección: Aplicabilidad -->
            <div>
                <flux:heading size="sm" class="mb-4">{{ __('Aplicabilidad') }}</flux:heading>
                <div class="grid gap-6 md:grid-cols-2">
                    
                    <!-- Tarjeta: Oficinas -->
                    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-5 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Oficinas aplicables') }}</flux:heading>
                            <flux:checkbox wire:model.live="appliesToAllOffices" :label="__('Aplica a todas')" data-test="promotion-all-offices" />
                        </div>
                        
                        <div class="{{ $appliesToAllOffices ? 'opacity-50 pointer-events-none' : '' }}">
                            <flux:modal.trigger name="select-offices">
                                <flux:button
                                    variant="outline"
                                    class="w-full justify-between"
                                    :disabled="$appliesToAllOffices"
                                    data-test="promotion-offices-trigger"
                                >
                                    @if (empty($selectedOfficeIds))
                                        <span class="text-zinc-500">{{ __('Selecciona oficinas') }}</span>
                                    @else
                                        <span>{{ count($selectedOfficeIds) }} {{ __('seleccionadas') }}</span>
                                    @endif
                                    <flux:icon name="chevron-down" class="size-4" />
                                </flux:button>
                            </flux:modal.trigger>
                        </div>

                        <flux:modal name="select-offices" class="min-w-[22rem]" x-on:close="$wire.dispatch('officesSelected')">
                            <flux:heading>{{ __('Oficinas aplicables') }}</flux:heading>
                            <flux:subheading>{{ __('Selecciona las oficinas donde aplica esta promoción.') }}</flux:subheading>

                            <div class="mt-4 flex flex-col gap-2 max-h-[60vh] overflow-y-auto pr-2">
                                @forelse ($this->offices as $office)
                                    <label
                                        class="flex cursor-pointer items-center gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm text-zinc-900 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                        wire:key="promotion-office-{{ $office->id }}"
                                    >
                                        <flux:checkbox wire:model.live="selectedOfficeIds" :value="$office->id" />
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ $office->name }}</span>
                                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $office->code }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="py-4 text-center text-sm text-zinc-500">
                                        {{ __('No hay oficinas registradas.') }}
                                    </div>
                                @endforelse
                            </div>
                        </flux:modal>
                    </div>

                    <!-- Tarjeta: Categorías -->
                    <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-5 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ __('Categorías aplicables') }}</flux:heading>
                            <flux:checkbox wire:model.live="appliesToAllCategories" :label="__('Aplica a todas')" data-test="promotion-all-categories" />
                        </div>
                        
                        <div class="{{ $appliesToAllCategories ? 'opacity-50 pointer-events-none' : '' }}">
                            <flux:modal.trigger name="select-categories">
                                <flux:button
                                    variant="outline"
                                    class="w-full justify-between"
                                    :disabled="$appliesToAllCategories"
                                    data-test="promotion-categories-trigger"
                                >
                                    @if (empty($selectedCategoryIds))
                                        <span class="text-zinc-500">{{ __('Selecciona categorías') }}</span>
                                    @else
                                        <span>{{ count($selectedCategoryIds) }} {{ __('seleccionadas') }}</span>
                                    @endif
                                    <flux:icon name="chevron-down" class="size-4" />
                                </flux:button>
                            </flux:modal.trigger>
                        </div>

                        <flux:modal name="select-categories" class="min-w-[22rem]" x-on:close="$wire.dispatch('categoriesSelected')">
                            <flux:heading>{{ __('Categorías aplicables') }}</flux:heading>
                            <flux:subheading>{{ __('Selecciona las categorías donde aplica esta promoción.') }}</flux:subheading>

                            <div class="mt-4 flex flex-col gap-2 max-h-[60vh] overflow-y-auto pr-2">
                                @forelse ($this->categories as $category)
                                    <label
                                        class="flex cursor-pointer items-center gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm text-zinc-900 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                                        wire:key="promotion-category-{{ $category->id }}"
                                    >
                                        <flux:checkbox wire:model.live="selectedCategoryIds" :value="$category->id" />
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ $category->name }}</span>
                                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $category->code }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="py-4 text-center text-sm text-zinc-500">
                                        {{ __('No hay categorías registradas.') }}
                                    </div>
                                @endforelse
                            </div>
                        </flux:modal>
                    </div>

                </div>
            </div>

            <!-- Acciones -->
            <div class="flex items-center gap-3 pt-4">
                <flux:button variant="primary" type="submit" data-test="promotion-save">
                    {{ $editingUuid ? __('Actualizar') : __('Crear') }}
                </flux:button>
                @if ($editingUuid)
                    <flux:button type="button" variant="ghost" wire:click="cancelEdit" data-test="promotion-cancel">
                        {{ __('Cancelar') }}
                    </flux:button>
                @endif
            </div>
        </form>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <flux:heading>{{ __('Promociones activas') }}</flux:heading>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <flux:select wire:model.live="typeFilter" :label="__('Tipo')" data-test="promotion-filter-type">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    <flux:select.option value="seasonal">{{ __('Temporada') }}</flux:select.option>
                    <flux:select.option value="volume">{{ __('Volumen') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="statusFilter" :label="__('Estado')" data-test="promotion-filter-status">
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('Activa') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactiva') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Buscar')" placeholder="Nombre" class="md:w-64" data-test="promotion-search" />
            </div>
        </div>

        <flux:table :paginate="$this->promotions">
            <flux:table.columns>
                <flux:table.column>{{ __('Nombre') }}</flux:table.column>
                <flux:table.column>{{ __('Tipo') }}</flux:table.column>
                <flux:table.column>{{ __('Descuento') }}</flux:table.column>
                <flux:table.column>{{ __('Vigencia') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column>{{ __('Acciones') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->promotions as $promotion)
                    <flux:table.row :key="$promotion->id">
                        <flux:table.cell class="font-medium">{{ $promotion->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$promotion->type === 'seasonal' ? 'sky' : 'emerald'">
                                {{ $promotion->type === 'seasonal' ? __('Temporada') : __('Volumen') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format((float) $promotion->discount_value, 2) }}
                            {{ $promotion->discount_type === 'percentage' ? '%' : '' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $promotion->valid_from->format('d/m/Y') }} – {{ $promotion->valid_to->format('d/m/Y') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$promotion->status === 'active' ? 'emerald' : 'zinc'">
                                {{ $promotion->status === 'active' ? __('Activa') : __('Inactiva') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit('{{ $promotion->uuid }}')" data-test="promotion-edit-{{ $promotion->id }}">
                                    {{ __('Editar') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" :icon="$promotion->status === 'active' ? 'pause' : 'play'" wire:click="toggleStatus('{{ $promotion->uuid }}')" data-test="promotion-toggle-{{ $promotion->id }}">
                                    {{ $promotion->status === 'active' ? __('Desactivar') : __('Activar') }}
                                </flux:button>
                                <flux:button size="sm" variant="danger" icon="trash" wire:click="delete('{{ $promotion->uuid }}')" wire:confirm="{{ __('¿Eliminar esta promoción?') }}" data-test="promotion-delete-{{ $promotion->id }}">
                                    {{ __('Eliminar') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="py-8 text-center">
                                <flux:text>{{ __('No hay promociones registradas.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
