<?php

use App\Models\RateImport;
use App\Models\Supplier;
use App\Modules\Import\Application\DTOs\CreateRateImportData;
use App\Modules\Import\Application\UseCases\CreateRateImport;
use App\Support\RateImportSpreadsheet;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Importaciones')] class extends Component {
    use WithFileUploads;

    public ?int $supplierId = null;

    public ?TemporaryUploadedFile $file = null;

    public bool $formatIsValid = false;

    public string $validationMessage = '';

    public int $detectedRows = 0;

    /**
     * @var list<array<string, mixed>>
     */
    public array $parsedRows = [];

    public function mount(): void
    {
        $this->supplierId = Auth::user()->supplier_id;
    }

    public function updatedFile(RateImportSpreadsheet $spreadsheet): void
    {
        $this->resetValidationState();

        $this->validate([
            'file' => [
                'required',
                File::types(['xlsx'])->max(10 * 1024),
            ],
            'supplierId' => ['required', 'integer', Rule::exists('suppliers', 'id')],
        ]);

        try {
            $parsed = $spreadsheet->read($this->file->getRealPath());
        } catch (Throwable $exception) {
            $this->validationMessage = $exception->getMessage();
            $this->addError('file', $this->validationMessage);

            return;
        }

        if ($parsed['missing_headers'] !== []) {
            $this->validationMessage = __('Faltan columnas: :columns', [
                'columns' => implode(', ', $parsed['missing_headers']),
            ]);
            $this->addError('file', $this->validationMessage);

            return;
        }

        if ($parsed['rows'] === []) {
            $this->validationMessage = __('El archivo tiene encabezados válidos, pero no contiene filas.');
            $this->addError('file', $this->validationMessage);

            return;
        }

        $this->parsedRows = $parsed['rows'];
        $this->detectedRows = count($parsed['rows']);
        $this->formatIsValid = true;
        $this->validationMessage = __('Formato validado.');
    }

    public function upload(CreateRateImport $createRateImport): void
    {
        if (! $this->formatIsValid || $this->file === null || $this->parsedRows === []) {
            $this->addError('file', __('Valida un Excel con el formato correcto antes de cargar.'));

            return;
        }

        $storedPath = $this->file->store('imports');
        $originalFilename = $this->file->getClientOriginalName();

        if (! Auth::user()->supplier_id) {
            $user = Auth::user();
            $originalSupplierId = $user->supplier_id;
            $user->forceFill(['supplier_id' => $this->supplierId]);
        }

        try {
            $createRateImport->handle(new CreateRateImportData(
                originalFilename: $originalFilename,
                storedPath: $storedPath,
                uploadedBy: Auth::id(),
                rows: $this->parsedRows,
            ));
        } finally {
            if (isset($user, $originalSupplierId)) {
                $user->forceFill(['supplier_id' => $originalSupplierId]);
            }
        }

        $this->file = null;
        $this->resetValidationState();

        Flux::toast(variant: 'success', text: __('Carga registrada.'));
    }

    protected function resetValidationState(): void
    {
        $this->resetErrorBag();
        $this->formatIsValid = false;
        $this->validationMessage = '';
        $this->detectedRows = 0;
        $this->parsedRows = [];
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
     * @return Collection<int, RateImport>
     */
    #[Computed]
    public function recentImports(): Collection
    {
        return RateImport::query()
            ->with('supplier:id,name,code')
            ->forSupplier(Auth::user()->supplier_id)
            ->latest()
            ->limit(5)
            ->get();
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Importaciones') }}</flux:heading>
        <flux:text class="max-w-3xl">
            {{ __('Carga archivos Excel de tarifas. Primero se valida que el archivo tenga el formato esperado; después puedes proceder con la carga.') }}
        </flux:text>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <form
            wire:submit="upload"
            class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-cancel="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
        >
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Nueva carga') }}</flux:heading>
                    <flux:text>{{ __('Formato requerido: columnas office_code, vehicle_class, acriss_code, rate_plan_code, currency, base_price, valid_from, valid_to.') }}</flux:text>
                </div>

                @if (! Auth::user()->supplier_id)
                    <flux:select wire:model.live="supplierId" :label="__('Proveedor')" data-test="import-supplier">
                        <flux:select.option value="">{{ __('Selecciona proveedor') }}</flux:select.option>
                        @foreach ($this->suppliers as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <div>
                        <flux:heading size="sm">{{ __('Plantilla por proveedor') }}</flux:heading>
                        <flux:text class="text-sm">
                            {{ __('Descarga el Excel con encabezado de Outlet Car Rental, logo, nombre, número del proveedor y columnas de precios.') }}
                        </flux:text>
                    </div>

                    <div>
                        @if ($supplierId)
                            <flux:button as="a" :href="route('portal.imports.template', $supplierId)" icon="arrow-down-tray" data-test="import-template-download">
                                {{ __('Descargar plantilla Excel') }}
                            </flux:button>
                        @else
                            <flux:button type="button" icon="arrow-down-tray" disabled data-test="import-template-download-disabled">
                                {{ __('Selecciona proveedor para descargar') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                <flux:input wire:model="file" :label="__('Archivo Excel')" type="file" accept=".xlsx" data-test="import-file" />

                <div x-show="uploading" x-cloak>
                    <flux:field>
                        <flux:label>{{ __('Subiendo archivo') }}</flux:label>
                        <flux:progress x-bind:value="progress" color="blue" />
                        <flux:description><span x-text="`${progress}%`"></span></flux:description>
                    </flux:field>
                </div>

                @if ($formatIsValid)
                    <div class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200" data-test="import-valid">
                        <flux:icon.check-circle class="size-5" />
                        <div>
                            <div class="font-medium">{{ $validationMessage }}</div>
                            <div class="text-sm">{{ __(':count filas detectadas.', ['count' => $detectedRows]) }}</div>
                        </div>
                    </div>
                @elseif ($validationMessage !== '')
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200" data-test="import-invalid">
                        {{ $validationMessage }}
                    </div>
                @endif

                <div wire:loading wire:target="upload" class="space-y-2">
                    <flux:progress value="75" color="green" />
                    <flux:text>{{ __('Registrando carga y filas de staging...') }}</flux:text>
                </div>

                <div class="flex justify-end">
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="check"
                        class="bg-green-600! hover:bg-green-700!"
                        :disabled="! $formatIsValid"
                        data-test="import-submit"
                    >
                        {{ __('Proceder a cargar') }}
                    </flux:button>
                </div>
            </div>
        </form>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('Últimas 5 cargas') }}</flux:heading>

            <div class="mt-4 space-y-3">
                @forelse ($this->recentImports as $import)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="recent-import">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $import->original_filename }}</div>
                                <flux:text class="text-sm">{{ $import->supplier?->code }} · {{ $import->created_at?->format('Y-m-d H:i') }}</flux:text>
                            </div>
                            <flux:badge color="zinc">{{ $import->status }}</flux:badge>
                        </div>

                        <div class="mt-2 grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <div class="text-zinc-500">{{ __('Total') }}</div>
                                <div class="font-medium">{{ $import->total_rows }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('Válidas') }}</div>
                                <div class="font-medium">{{ $import->valid_rows }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('Inválidas') }}</div>
                                <div class="font-medium">{{ $import->invalid_rows }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <flux:text class="block py-8 text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('Aún no hay cargas registradas.') }}
                    </flux:text>
                @endforelse
            </div>
        </div>
    </div>
</section>
