<x-layouts::auth.split :title="__('Log in')">
    <div class="rounded-lg border border-zinc-200 bg-white px-6 py-7 shadow-sm sm:px-8 sm:py-8">
        <div class="mb-7 space-y-3">
            <div class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">
                <flux:icon name="lock-closed" class="size-3.5" />
                {{ __('Portal seguro') }}
            </div>
            <div class="space-y-2">
                <flux:heading size="xl">{{ __('Iniciar sesión') }}</flux:heading>
                <flux:subheading class="leading-6">
                    {{ __('Accede con tus credenciales corporativas para gestionar tarifas, oficinas, disponibilidad y reservas.') }}
                </flux:subheading>
            </div>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-700" :status="session('status')" />

        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="flex flex-col gap-5 [&_[data-flux-label]]:font-semibold [&_[data-flux-label]]:text-zinc-900"
            x-data="{ submitting: false }"
            x-on:submit="submitting = true"
        >
            @csrf

            <flux:input
                name="username"
                :label="__('Usuario')"
                :value="old('username')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="usuario"
                input:class="text-zinc-950! placeholder:text-zinc-600!"
            />

            <!-- Password -->
            <div>
                <flux:input
                    name="password"
                    :label="__('Contraseña')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Contraseña')"
                    input:class="text-zinc-950! placeholder:text-zinc-600!"
                    viewable
                />
            </div>

            <div class="flex items-center justify-between gap-4">
                <!-- Remember Me -->
                <flux:checkbox name="remember" :label="__('Recordar este equipo')" :checked="old('remember')" />

                @if (Route::has('password.request'))
                    <flux:link class="text-sm font-medium text-sky-700! hover:text-sky-800!" :href="route('password.request')" wire:navigate>
                        {{ __('Recuperar acceso') }}
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full bg-sky-600! text-white! hover:bg-sky-700!" x-bind:disabled="submitting" data-test="login-button">
                    <span x-show="! submitting">{{ __('Entrar al portal') }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('Ingresando...') }}
                    </span>
                </flux:button>
            </div>
        </form>

        <div class="mt-6 space-x-1 text-center text-sm text-zinc-600 rtl:space-x-reverse">
            <span>{{ __('¿No tienes cuenta?') }}</span>
            <flux:link :href="route('register')" class="font-medium text-sky-700! hover:text-sky-800!" wire:navigate>{{ __('Solicita el alta') }}</flux:link>
        </div>
    </div>
</x-layouts::auth.split>
