<?php

namespace App\Providers;

use App\Modules\Booking\Application\Contracts\BookingRepository;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingRepository;
use App\Modules\Import\Application\Contracts\RateImportRepository;
use App\Modules\Import\Infrastructure\Repositories\EloquentRateImportRepository;
use App\Modules\Pricing\Application\Contracts\RateRepository;
use App\Modules\Pricing\Infrastructure\Repositories\EloquentRateRepository;
use App\Modules\Supplier\Application\Contracts\SupplierRepository;
use App\Modules\Supplier\Application\Contracts\SupplierUserRepository;
use App\Modules\Supplier\Infrastructure\Repositories\EloquentSupplierRepository;
use App\Modules\Supplier\Infrastructure\Repositories\EloquentSupplierUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BookingRepository::class, EloquentBookingRepository::class);
        $this->app->bind(RateImportRepository::class, EloquentRateImportRepository::class);
        $this->app->bind(RateRepository::class, EloquentRateRepository::class);
        $this->app->bind(SupplierRepository::class, EloquentSupplierRepository::class);
        $this->app->bind(SupplierUserRepository::class, EloquentSupplierUserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
