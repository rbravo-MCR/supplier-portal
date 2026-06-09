<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased text-zinc-950">
        <div class="grid min-h-dvh lg:grid-cols-[1.05fr_0.95fr]">
            <aside class="relative hidden min-h-dvh overflow-hidden bg-zinc-950 px-10 py-10 text-white lg:flex lg:flex-col">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,rgba(14,165,233,0.22),transparent_34%),radial-gradient(circle_at_75%_12%,rgba(250,204,21,0.14),transparent_30%),linear-gradient(135deg,#09090b_0%,#18181b_55%,#0f172a_100%)]"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <a href="{{ route('home') }}" class="flex items-center gap-4" wire:navigate>
                        <span class="flex h-16 w-56 items-center justify-start">
                            <x-app-logo-icon class="max-h-16 w-56" />
                        </span>
                        <span class="sr-only">{{ config('app.name', 'Supplier portal') }}</span>
                    </a>
                    <span class="rounded-full border border-white/15 px-3 py-1 text-xs font-medium text-white/75">
                        {{ __('Acceso seguro') }}
                    </span>
                </div>

                <div class="relative z-10 mt-auto max-w-xl space-y-8">
                    <div class="space-y-4">
                        <flux:heading size="xl" class="text-white">
                            {{ __('Portal operativo para proveedores') }}
                        </flux:heading>
                        <p class="max-w-lg text-base leading-7 text-zinc-300">
                            {{ __('Centraliza tarifas, disponibilidad, oficinas y reservas en un entorno controlado por roles, proveedor y autenticación segura.') }}
                        </p>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                            <div class="flex items-start gap-3">
                                <flux:icon name="shield-check" class="mt-0.5 size-5 shrink-0 text-sky-300" />
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ __('Validación de credenciales') }}</p>
                                    <p class="mt-1 text-sm leading-6 text-zinc-300">{{ __('Fortify verifica email, contraseña, estado del usuario y pertenencia a proveedor activo antes de abrir sesión.') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                            <div class="flex items-start gap-3">
                                <flux:icon name="key" class="mt-0.5 size-5 shrink-0 text-yellow-300" />
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ __('Segundo factor cuando aplica') }}</p>
                                    <p class="mt-1 text-sm leading-6 text-zinc-300">{{ __('Los usuarios con 2FA activo continúan al desafío de verificación antes de entrar al panel.') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                            <div class="flex items-start gap-3">
                                <flux:icon name="building-office-2" class="mt-0.5 size-5 shrink-0 text-emerald-300" />
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ __('Redirección por perfil') }}</p>
                                    <p class="mt-1 text-sm leading-6 text-zinc-300">{{ __('Administradores van al panel interno y usuarios de proveedor entran directo a su operación.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="flex min-h-dvh items-center justify-center px-5 py-8 sm:px-8 lg:px-12">
                <div class="w-full max-w-[28rem]">
                    <a href="{{ route('home') }}" class="mb-8 flex justify-center lg:hidden" wire:navigate>
                        <span class="flex h-20 w-72 items-center justify-center">
                            <x-app-logo-icon class="max-h-20 w-72" />
                        </span>
                        <span class="sr-only">{{ config('app.name', 'Supplier portal') }}</span>
                    </a>

                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
