<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased text-zinc-950">
        <div class="grid min-h-dvh lg:grid-cols-[1.05fr_0.95fr]">
            <aside class="relative hidden min-h-dvh overflow-hidden bg-zinc-950 px-10 py-10 text-white lg:flex lg:flex-col">
                <img
                    src="{{ asset('images/foto_renta.webp') }}"
                    alt=""
                    class="absolute inset-0 h-full w-full scale-105 object-cover blur-xs"
                >
                <div class="absolute inset-0 bg-gradient-to-b from-black/55 via-black/20 to-black/70"></div>
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
                        <flux:heading size="xl" class="text-white drop-shadow">
                            {{ __('Portal operativo para proveedores') }}
                        </flux:heading>
                        <p class="max-w-lg text-base leading-7 text-zinc-100 drop-shadow">
                            {{ __('Centraliza tarifas, disponibilidad, oficinas y reservas en un entorno controlado por roles, proveedor y autenticación segura.') }}
                        </p>
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
