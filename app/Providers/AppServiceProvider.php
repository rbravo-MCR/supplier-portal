<?php

namespace App\Providers;

use App\Modules\Booking\Application\Contracts\BookingRepository;
use App\Modules\Booking\Infrastructure\Repositories\EloquentBookingRepository;
use App\Modules\Import\Application\Contracts\RateImportRepository;
use App\Modules\Import\Infrastructure\Repositories\EloquentRateImportRepository;
use App\Modules\Pricing\Application\Contracts\RateRepository;
use App\Modules\Pricing\Infrastructure\Repositories\EloquentRateRepository;
use App\Modules\Promotions\Application\Contracts\PromotionRepository;
use App\Modules\Promotions\Infrastructure\Repositories\EloquentPromotionRepository;
use App\Modules\Supplier\Application\Contracts\SupplierRepository;
use App\Modules\Supplier\Application\Contracts\SupplierUserRepository;
use App\Modules\Supplier\Infrastructure\Repositories\EloquentSupplierRepository;
use App\Modules\Supplier\Infrastructure\Repositories\EloquentSupplierUserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(PromotionRepository::class, EloquentPromotionRepository::class);
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
        $this->configureRateLimiters();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction() || config('database.default') !== 'sqlite',
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

    /**
     * Configure route rate limiters.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('i18n-locale', function (Request $request): Limit {
            $key = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });
    }
}
