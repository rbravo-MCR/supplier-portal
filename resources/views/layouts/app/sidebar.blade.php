<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            [data-flux-sidebar-item][data-current] {
                background-color: rgb(254 240 138) !important;
                border-color: rgb(250 204 21) !important;
                color: rgb(63 63 70) !important;
            }

            .dark [data-flux-sidebar-item][data-current] {
                background-color: rgb(250 204 21) !important;
                border-color: rgb(234 179 8) !important;
                color: rgb(24 24 27) !important;
            }
        </style>
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <livewire:team-switcher />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Operación')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('portal.bookings')" :current="request()->routeIs('portal.bookings')" wire:navigate>
                        {{ __('Reservas') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="currency-dollar" :href="route('portal.prices')" :current="request()->routeIs('portal.prices')" wire:navigate>
                        {{ __('Precios') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-up-tray" :href="route('portal.imports')" :current="request()->routeIs('portal.imports')" wire:navigate>
                        {{ __('Importaciones') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Administración')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('portal.users')" :current="request()->routeIs('portal.users')" wire:navigate>
                        {{ __('Usuarios') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-office-2" :href="route('portal.suppliers')" :current="request()->routeIs('portal.suppliers')" wire:navigate>
                        {{ __('Proveedores') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="map-pin" :href="route('portal.offices')" :current="request()->routeIs('portal.offices')" wire:navigate>
                        {{ __('Oficinas') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="squares-2x2" :href="route('portal.categories')" :current="request()->routeIs('portal.categories')" wire:navigate>
                        {{ __('Categorías') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Sistema')" class="grid">
                    <flux:sidebar.item icon="shield-check" :href="route('portal.audit')" :current="request()->routeIs('portal.audit')" wire:navigate>
                        {{ __('Auditoría') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="heart" :href="route('portal.status')" :current="request()->routeIs('portal.status')" wire:navigate>
                        {{ __('Estado') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                        {{ __('Configuración') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <livewire:create-team-modal />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @include('partials.portal-working-indicator')

        @fluxScripts
    </body>
</html>
