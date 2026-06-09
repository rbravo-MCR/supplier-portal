<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 antialiased">
        <div class="flex min-h-svh items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-md rounded-lg border border-zinc-200 bg-white px-8 py-8 shadow-sm">
                <div class="flex flex-col gap-7">
                    <a href="{{ route('home') }}" class="flex justify-center" wire:navigate>
                        <span class="flex h-20 w-72 items-center justify-center">
                            <x-app-logo-icon class="max-h-20 w-72" />
                        </span>
                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
