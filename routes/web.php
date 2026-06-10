<?php

use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Supplier;
use App\Modules\System\Application\Services\HealthCheckService;
use App\Support\RateImportTemplateSpreadsheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::guest()) {
        return to_route('login');
    }

    if (in_array(Auth::user()->role, ['supplier_admin', 'supplier_reservations', 'supplier_pricing', 'supplier_user'], true)) {
        return to_route('supplier.dashboard');
    }

    return to_route('admin.dashboard');
})->name('home');

Route::get('health', fn (HealthCheckService $health) => response()->json($health->all()))
    ->name('health');

Route::get('health/db', fn (HealthCheckService $health) => response()->json($health->database()))
    ->name('health.db');

Route::get('health/redis', fn (HealthCheckService $health) => response()->json($health->redis()))
    ->name('health.redis');

Route::get('health/queue', fn (HealthCheckService $health) => response()->json($health->queue()))
    ->name('health.queue');

Route::get('health/storage', fn (HealthCheckService $health) => response()->json($health->storage()))
    ->name('health.storage');

Route::get('health/outbox', fn (HealthCheckService $health) => response()->json($health->outbox()))
    ->name('health.outbox');

Route::get('health/failed-jobs', fn (HealthCheckService $health) => response()->json($health->failedJobs()))
    ->name('health.failed-jobs');

Route::view('admin', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('admin.dashboard');

Route::view('supplier', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('supplier.dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('bookings', 'pages::bookings')->name('portal.bookings');

    Route::livewire('prices', 'pages::pricing')->name('portal.prices');

    Route::get('imports/template/{supplier:id}', function (Supplier $supplier, RateImportTemplateSpreadsheet $spreadsheet) {
        abort_if(Auth::user()->supplier_id && Auth::user()->supplier_id !== $supplier->id, 403);

        $path = $spreadsheet->create($supplier);

        return response()
            ->download($path, "plantilla-precios-{$supplier->code}.xlsx")
            ->deleteFileAfterSend();
    })->name('portal.imports.template');

    Route::livewire('imports', 'pages::imports')->name('portal.imports');

    Route::livewire('users', 'pages::users')->name('portal.users');

    Route::livewire('suppliers', 'pages::suppliers')->name('portal.suppliers');

    Route::livewire('offices', 'pages::offices')->name('portal.offices');

    Route::livewire('availabilities', 'pages::availabilities')->name('portal.availabilities');

    Route::livewire('categories', 'pages::categories')->name('portal.categories');

    Route::livewire('audit', 'pages::audit')->name('portal.audit');

    Route::view('status', 'portal-placeholder', [
        'title' => __('Estado'),
        'description' => __('Consulta salud del sistema, cola, storage, outbox y jobs fallidos.'),
    ])->name('portal.status');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
