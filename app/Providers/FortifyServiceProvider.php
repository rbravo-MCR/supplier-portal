<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Identity\Application\UseCases\CanAuthenticateUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $supplierCode = Str::lower((string) $request->input('supplier_code'));
            $username = Str::lower((string) $request->input(Fortify::username()));
            $isInternalLogin = $supplierCode === '' || $supplierCode === '__internal';

            $users = User::query()
                ->with('supplier')
                ->whereRaw('LOWER(username) = ?', [$username])
                ->where(function ($query) use ($isInternalLogin, $supplierCode): void {
                    $query->whereNull('supplier_id');

                    if (! $isInternalLogin) {
                        $query->orWhereHas(
                            'supplier',
                            fn ($query) => $query->whereRaw('LOWER(code) = ?', [$supplierCode]),
                        );
                    }
                })
                ->orderByRaw('supplier_id IS NULL')
                ->get();

            foreach ($users as $user) {
                if (
                    Hash::check((string) $request->input('password'), $user->password)
                    && app(CanAuthenticateUser::class)->handle($user)
                ) {
                    return $user;
                }
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login', [
            'suppliers' => Supplier::query()
                ->select(['name', 'code'])
                ->active()
                ->orderBy('name')
                ->get(),
        ]));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input('supplier_code')).'|'.Str::lower($request->input(Fortify::username())).'|'.$request->ip(),
            );

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
