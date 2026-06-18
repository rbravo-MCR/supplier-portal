<?php

use App\Http\Middleware\EnsureHealthSecret;
use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Supplier;
use App\Modules\System\Application\Services\HealthCheckService;
use App\Support\RateImportTemplateSpreadsheet;
use App\Support\SupportedLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('/', function () {
    if (Auth::guest()) {
        return to_route('login');
    }

    if (in_array(Auth::user()->role, ['supplier_admin', 'supplier_reservations', 'supplier_pricing', 'supplier_user'], true)) {
        return to_route('supplier.dashboard');
    }

    return to_route('admin.dashboard');
})->name('home');

Route::middleware([EnsureHealthSecret::class])->group(function () {
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
});

Route::view('admin', 'dashboard')
    ->middleware(['auth'])
    ->name('admin.dashboard');

Route::view('supplier', 'dashboard')
    ->middleware(['auth'])
    ->name('supplier.dashboard');

Route::middleware(['auth'])->group(function () {
    Route::post('settings/locale', function (Request $request) {
        $validated = $request->validate([
            'locale' => ['bail', 'required', 'string', Rule::in(SupportedLocale::codes())],
        ]);

        $locale = SupportedLocale::normalize($validated['locale']);
        $user = $request->user();
        $oldLocale = SupportedLocale::normalize($user->preferred_locale);

        $user->forceFill([
            'preferred_locale' => $locale,
        ])->save();

        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        if ($oldLocale !== $locale) {
            Log::info('User preferred locale changed', [
                'user_id' => $user->id,
                'old_locale' => $oldLocale,
                'new_locale' => $locale,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'changed_at' => now()->toISOString(),
            ]);
        }

        return back();
    })->middleware('throttle:i18n-locale')->name('locale.update');

    Route::livewire('bookings', 'pages::bookings')->name('portal.bookings');

    Route::livewire('prices', 'pages::pricing')->name('portal.prices');

    Route::livewire('promotions', 'pages::promotions')->name('portal.promotions');

    Route::get('imports/template/{supplier:id}', function (Supplier $supplier, RateImportTemplateSpreadsheet $spreadsheet) {
        abort_if(Auth::user()->supplier_id && Auth::user()->supplier_id !== $supplier->id, 403);

        $locale = SupportedLocale::normalize(App::currentLocale());
        $spreadsheet->ensureLocalizedTemplates($supplier);
        $path = $spreadsheet->create($supplier, $locale);

        return response()
            ->download($path, "supplier-prices-{$supplier->code}-{$locale}.xlsx")
            ->deleteFileAfterSend();
    })->name('portal.imports.template');

    Route::get('imports/errors/{filename}', function (string $filename) {
        abort_unless(preg_match('/^rate-import-errors-[a-f0-9-]+\.xlsx$/', $filename) === 1, 404);

        $path = storage_path('app/import-errors/'.$filename);

        abort_unless(File::exists($path), 404);

        return response()->download($path, 'rate-import-errors.xlsx');
    })->name('portal.imports.errors');

    Route::livewire('imports', 'pages::imports')->name('portal.imports');

    Route::livewire('users', 'pages::users')->name('portal.users');

    Route::livewire('suppliers', 'pages::suppliers')->name('portal.suppliers');

    Route::livewire('offices', 'pages::offices')->name('portal.offices');

    Route::livewire('availabilities', 'pages::availabilities')->name('portal.availabilities');

    Route::livewire('categories', 'pages::categories')->name('portal.categories');

    Route::livewire('audit', 'pages::audit')->name('portal.audit');

    Route::get('status', fn (HealthCheckService $health) => view('portal-status', [
        'health' => $health->all(),
    ]))->name('portal.status');
});

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
