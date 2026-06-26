<?php

use App\Models\RateImport;
use App\Models\Office;
use App\Models\Supplier;
use App\Modules\Import\Application\DTOs\CreateRateImportData;
use App\Modules\Import\Application\UseCases\CreateRateImport;
use App\Modules\Import\Application\UseCases\PublishRateImportRows;
use App\Support\RateImportErrorSpreadsheet;
use App\Support\RateImportSpreadsheet;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    /**
     * @var list<int|string>
     */
    public array $selectedOfficeIds = [];

    public bool $formatIsValid = false;

    public string $validationMessage = '';

    public int $detectedRows = 0;

    /**
     * @var array{processed: int, successful: int, failed: int}
     */
    public array $importSummary = [
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
    ];

    public string $errorReportFilename = '';

    public string $successMessage = '';

    public int $lastImportedRows = 0;

    /**
     * @var list<array<string, mixed>>
     */
    public array $parsedRows = [];

    public function mount(): void
    {
        $this->supplierId = Auth::user()->supplier_id;
    }

    public function updatedFile(RateImportSpreadsheet $spreadsheet, RateImportErrorSpreadsheet $errorSpreadsheet): void
    {
        $this->resetValidationState();
        $this->resetSuccessState();

        $this->validate([
            'file' => [
                'required',
                File::types(['xlsx'])->max(10 * 1024),
            ],
            'supplierId' => ['required', 'integer', Rule::exists('suppliers', 'id')],
        ]);

        try {
            $parsed = $spreadsheet->readForSupplier($this->file->getRealPath(), $this->resolvedSupplierId(), app()->getLocale());
        } catch (Throwable $exception) {
            $this->validationMessage = $exception->getMessage();
            $this->addError('file', $this->validationMessage);

            return;
        }

        if ($parsed['missing_headers'] !== []) {
            $this->validationMessage = __('Faltan columnas: :columns', [
                'columns' => implode(', ', array_map($this->columnLabel(...), $parsed['missing_headers'])),
            ]);
            $this->importSummary = [
                'processed' => $parsed['summary']['processed'],
                'successful' => 0,
                'failed' => $parsed['summary']['processed'],
            ];
            $this->storeErrorReport($errorSpreadsheet, array_map(fn (string $header): array => [
                'row' => 1,
                'field' => $header,
                'message' => __('Falta la columna :field.', ['field' => $this->columnLabel($header)]),
            ], $parsed['missing_headers']));
            $this->addError('file', $this->validationMessage);

            return;
        }

        if ($parsed['rows'] === []) {
            $this->validationMessage = __('El archivo tiene encabezados válidos, pero no contiene filas.');
            $this->addError('file', $this->validationMessage);

            return;
        }

        if ($parsed['errors'] !== []) {
            $this->importSummary = $parsed['summary'];
            $this->storeErrorReport($errorSpreadsheet, $parsed['errors']);
            $this->validationMessage = __('El archivo contiene filas con error.');
            $this->addError('file', $this->validationMessage);

            return;
        }

        $this->parsedRows = $parsed['rows'];
        $this->detectedRows = count($parsed['rows']);
        $this->importSummary = $parsed['summary'];
        $this->formatIsValid = true;
        $this->validationMessage = __('Formato validado.');
    }

    public function updatedSupplierId(): void
    {
        if (Auth::user()->supplier_id) {
            $this->supplierId = Auth::user()->supplier_id;
        }

        $this->selectedOfficeIds = [];
        $this->resetValidationState();
        $this->resetSuccessState();
    }

    public function commitImport(CreateRateImport $createRateImport, PublishRateImportRows $publishRateImportRows): void
    {
        $this->resetSuccessState();

        if (! $this->formatIsValid || $this->file === null || $this->parsedRows === []) {
            $this->addError('file', __('Valida un Excel con el formato correcto antes de cargar.'));

            return;
        }

        Log::info('Rate import commit started', [
            'user_id' => Auth::id(),
            'supplier_id' => $this->resolvedSupplierId(),
            'rows' => count($this->parsedRows),
        ]);

        $originalFilename = $this->file->getClientOriginalName();

        try {
            $storedPath = $this->file->store('imports');

            $rateImport = $createRateImport->handle(new CreateRateImportData(
                originalFilename: $originalFilename,
                storedPath: $storedPath,
                uploadedBy: Auth::id(),
                rows: $this->parsedRows,
                supplierId: $this->resolvedSupplierId(),
            ));

            $publishResult = $publishRateImportRows->handle($rateImport, Auth::id());

            RateImport::query()
                ->where('supplier_id', $this->resolvedSupplierId())
                ->where('id', '!=', $rateImport->id)
                ->get()
                ->each(function (RateImport $import) {
                    if ($import->stored_path && Storage::exists($import->stored_path)) {
                        Storage::delete($import->stored_path);
                    }
                    $import->rows()->delete();
                    $import->delete();
                });
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Rate import commit failed', [
                'user_id' => Auth::id(),
                'supplier_id' => $this->resolvedSupplierId(),
                'filename' => $originalFilename,
                'message' => $exception->getMessage(),
            ]);

            $this->validationMessage = __('No se pudo guardar la importación. Intenta nuevamente.');
            $this->addError('file', $this->validationMessage);

            return;
        }

        if ($publishResult['errors'] !== []) {
            $this->importSummary = [
                'processed' => $rateImport->total_rows,
                'successful' => 0,
                'failed' => count($publishResult['errors']),
            ];
            $this->validationMessage = __('No se pudieron publicar los precios en la tabla final.');
            $this->addError('file', $this->validationMessage);

            Log::warning('Rate import publish failed validation', [
                'user_id' => Auth::id(),
                'supplier_id' => $rateImport->supplier_id,
                'rate_import_id' => $rateImport->id,
                'errors' => $publishResult['errors'],
            ]);

            return;
        }

        $this->file = null;
        $this->lastImportedRows = $publishResult['published'];
        $this->successMessage = __('Precios cargados correctamente. :count filas guardadas.', [
            'count' => format_number($this->lastImportedRows),
        ]);
        $this->resetValidationState();
        unset($this->recentImports);

        Log::info('Rate import commit completed', [
            'user_id' => Auth::id(),
            'supplier_id' => $rateImport->supplier_id,
            'rate_import_id' => $rateImport->id,
            'rows' => $publishResult['published'],
        ]);

        Flux::toast(variant: 'success', text: $this->successMessage);
    }

    protected function resetValidationState(): void
    {
        $this->resetErrorBag();
        $this->formatIsValid = false;
        $this->validationMessage = '';
        $this->detectedRows = 0;
        $this->parsedRows = [];
        $this->importSummary = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
        ];
        $this->errorReportFilename = '';
    }

    protected function resetSuccessState(): void
    {
        $this->successMessage = '';
        $this->lastImportedRows = 0;
    }

    protected function resolvedSupplierId(): int
    {
        $authenticatedSupplierId = Auth::user()->supplier_id;

        if ($authenticatedSupplierId) {
            $this->supplierId = $authenticatedSupplierId;

            return $authenticatedSupplierId;
        }

        return (int) $this->supplierId;
    }

    /**
     * @param  list<array{row: int, field: string, message: string}>  $errors
     */
    protected function storeErrorReport(RateImportErrorSpreadsheet $errorSpreadsheet, array $errors): void
    {
        $path = $errorSpreadsheet->create($this->importSummary, $errors, app()->getLocale());
        $this->errorReportFilename = basename($path);
    }

    protected function columnLabel(string $key): string
    {
        $column = config("imports.pricing_template.columns.{$key}", []);
        $translationKey = $column['translation_key'] ?? null;

        return is_string($translationKey) ? __($translationKey) : $key;
    }

    /**
     * @return Collection<int, array{key: string, label: string, required: bool, aliases: list<string>}>
     */
    #[Computed]
    public function importColumns(): Collection
    {
        return collect(config('imports.pricing_template.columns', []))
            ->map(fn (array $column, string $key): array => [
                'key' => $key,
                'label' => $this->columnLabel($key),
                'required' => (bool) ($column['required'] ?? false),
                'aliases' => $this->localizedColumnAliases($key, $column),
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $column
     * @return list<string>
     */
    protected function localizedColumnAliases(string $key, array $column): array
    {
        $aliases = $column['aliases'][app()->getLocale()] ?? [];

        return collect($aliases)
            ->push($key)
            ->map(fn (string $alias): string => trim($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function suppliers(): Collection
    {
        if (Auth::user()->supplier_id) {
            return Supplier::query()
                ->select(['id', 'name', 'code'])
                ->whereKey(Auth::user()->supplier_id)
                ->get();
        }

        return Supplier::query()
            ->select(['id', 'name', 'code'])
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Office>
     */
    #[Computed]
    public function offices(): Collection
    {
        $supplierId = Auth::user()->supplier_id ?: $this->supplierId;

        if (! $supplierId) {
            return collect();
        }

        return Office::query()
            ->select(['id', 'supplier_id', 'name', 'code', 'iata_code'])
            ->where('supplier_id', $supplierId)
            ->where('status', 'active')
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
            ->limit(1)
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
            wire:submit="commitImport"
            class="relative rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-cancel="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
        >
            <div
                wire:loading.flex
                wire:target="commitImport"
                class="absolute inset-0 z-10 hidden items-center justify-center rounded-lg bg-white/80 backdrop-blur-sm dark:bg-zinc-950/70"
                data-test="import-processing-overlay"
            >
                <div class="flex flex-col items-center gap-3 rounded-lg border border-zinc-200 bg-white p-4 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <svg class="size-6 animate-spin text-green-600" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <flux:text>{{ __('Registrando carga y filas de staging...') }}</flux:text>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Nueva carga') }}</flux:heading>
                    <flux:text>{{ __('Selecciona el proveedor, descarga la plantilla o carga un Excel que incluya los datos requeridos.') }}</flux:text>
                </div>

                <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" data-test="import-required-data">
                    <div class="border-b border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:heading size="sm">{{ __('Datos requeridos para el Excel') }}</flux:heading>
                        <flux:text class="text-sm">
                            {{ __('Usa estos encabezados en la primera fila. La importación también acepta los alias indicados.') }}
                        </flux:text>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-white text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                                <tr>
                                    <th scope="col" class="px-3 py-2">{{ __('Dato') }}</th>
                                    <th scope="col" class="px-3 py-2">{{ __('Clave') }}</th>
                                    <th scope="col" class="px-3 py-2">{{ __('Requisito') }}</th>
                                    <th scope="col" class="px-3 py-2">{{ __('Encabezados aceptados') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                                @foreach ($this->importColumns as $column)
                                    <tr wire:key="import-column-{{ $column['key'] }}">
                                        <td class="px-3 py-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $column['label'] }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $column['key'] }}</td>
                                        <td class="px-3 py-2">
                                            <flux:badge :color="$column['required'] ? 'red' : 'zinc'" size="sm">
                                                {{ $column['required'] ? __('Obligatoria') : __('Opcional') }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                            {{ implode(', ', $column['aliases']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if (! Auth::user()->supplier_id)
                    <flux:select wire:model.live="supplierId" :label="__('Proveedor')" data-test="import-supplier">
                        <flux:select.option value="">{{ __('Selecciona proveedor') }}</flux:select.option>
                        @foreach ($this->suppliers as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->name }} · {{ $supplier->code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800" data-test="import-current-supplier">
                        <flux:heading size="sm">{{ __('Proveedor') }}</flux:heading>
                        <flux:text>
                            {{ Auth::user()->supplier?->name }} · {{ Auth::user()->supplier?->code }}
                        </flux:text>
                    </div>
                @endif

                @if ($supplierId)
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800"
                        data-test="import-offices"
                    >
                        <div>
                            <flux:heading size="sm">{{ __('Oficinas registradas') }}</flux:heading>
                            <flux:text class="text-sm">
                                {{ __('Selecciona las oficinas del proveedor que aplican para esta importación.') }}
                            </flux:text>
                        </div>

                        @if ($this->offices->isEmpty())
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" data-test="import-offices-empty">
                                {{ __('Este proveedor no tiene oficinas activas registradas.') }}
                            </flux:text>
                        @else
                            <flux:checkbox.group wire:model="selectedOfficeIds" data-test="import-office-checkboxes">
                                <div class="grid gap-2 md:grid-cols-2">
                                    @foreach ($this->offices as $office)
                                        <label
                                            class="flex min-h-12 cursor-pointer items-start gap-3 rounded-md border border-zinc-200 bg-white p-3 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                                            wire:key="import-office-{{ $office->id }}"
                                        >
                                            <flux:checkbox :value="$office->id" />
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium">{{ $office->name }}</span>
                                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $office->code }}{{ $office->iata_code ? ' · '.$office->iata_code : '' }}
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </flux:checkbox.group>

                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400" data-test="import-offices-selected-count">
                                {{ __(':count oficinas seleccionadas.', ['count' => count($selectedOfficeIds)]) }}
                            </flux:text>
                        @endif
                    </div>
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
                            <div class="text-sm">{{ __(':count filas detectadas.', ['count' => format_number($detectedRows)]) }}</div>
                        </div>
                    </div>
                @elseif ($successMessage !== '')
                    <div class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200" data-test="import-success">
                        <flux:icon.check-circle class="size-5" />
                        <div>
                            <div class="font-medium">{{ $successMessage }}</div>
                            <div class="text-sm">{{ __('La importación quedó registrada en el historial.') }}</div>
                        </div>
                    </div>
                @elseif ($validationMessage !== '')
                    <div class="space-y-3 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200" data-test="import-invalid">
                        <div class="font-medium">{{ $validationMessage }}</div>
                        <div class="grid gap-2 text-sm md:grid-cols-3">
                            <div>{{ __('Total de filas procesadas') }}: {{ format_number($importSummary['processed']) }}</div>
                            <div>{{ __('Total de filas exitosas') }}: {{ format_number($importSummary['successful']) }}</div>
                            <div>{{ __('Total de filas con error') }}: {{ format_number($importSummary['failed']) }}</div>
                        </div>
                        @if ($errorReportFilename !== '')
                            <flux:button as="a" :href="route('portal.imports.errors', $errorReportFilename)" icon="arrow-down-tray" size="sm" data-test="import-errors-download">
                                {{ __('Archivo de errores descargable') }}
                            </flux:button>
                        @endif
                    </div>
                @endif

                <div wire:loading wire:target="commitImport" class="space-y-2">
                    <flux:progress value="75" color="green" />
                    <flux:text>{{ __('Registrando carga y filas de staging...') }}</flux:text>
                </div>

                <div class="flex justify-end">
                    @if ($formatIsValid)
                        <flux:button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="commitImport"
                            variant="primary"
                            icon="check"
                            class="bg-green-600! hover:bg-green-700!"
                            data-test="import-submit"
                        >
                            <span wire:loading.remove wire:target="commitImport">{{ __('Proceder a cargar') }}</span>
                            <span wire:loading wire:target="commitImport">{{ __('Registrando carga y filas de staging...') }}</span>
                        </flux:button>
                    @else
                        <flux:button
                            type="submit"
                            variant="primary"
                            icon="check"
                            class="bg-green-600! hover:bg-green-700!"
                            disabled
                            data-test="import-submit"
                        >
                            {{ __('Proceder a cargar') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </form>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>{{ __('Última carga') }}</flux:heading>

            <div class="mt-4 space-y-3">
                @forelse ($this->recentImports as $import)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="recent-import">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $import->original_filename }}</div>
                                <flux:text class="text-sm">{{ $import->supplier?->code }} · {{ format_datetime($import->created_at) }}</flux:text>
                            </div>
                            <flux:badge color="zinc">{{ $import->status }}</flux:badge>
                        </div>

                        <div class="mt-2 grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <div class="text-zinc-500">{{ __('Total') }}</div>
                                <div class="font-medium">{{ format_number($import->total_rows) }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('Válidas') }}</div>
                                <div class="font-medium">{{ format_number($import->valid_rows) }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('Inválidas') }}</div>
                                <div class="font-medium">{{ format_number($import->invalid_rows) }}</div>
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
