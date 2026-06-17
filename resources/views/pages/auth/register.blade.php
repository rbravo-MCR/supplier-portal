<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6 text-zinc-900">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <flux:select
                name="supplier_id"
                :label="__('Proveedor')"
                required
                class="bg-white! font-medium text-zinc-950! [color-scheme:light] [&>option]:bg-white [&>option]:text-zinc-950"
                data-test="register-supplier"
            >
                <flux:select.option value="">{{ __('Selecciona proveedor') }}</flux:select.option>
                @foreach ($suppliers as $supplier)
                    <flux:select.option :value="$supplier->id" :selected="(string) old('supplier_id') === (string) $supplier->id">
                        {{ \Illuminate\Support\Str::upper($supplier->name) }} · {{ $supplier->code }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            @if ($suppliers->isEmpty())
                <flux:text class="-mt-4 text-sm text-amber-700 dark:text-amber-400" data-test="register-suppliers-empty">
                    {{ __('No hay proveedores activos disponibles para registro.') }}
                </flux:text>
            @endif

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
                input:class="text-zinc-950! placeholder:text-zinc-600!"
            />

            <flux:input
                name="username"
                :label="__('Usuario')"
                :value="old('username')"
                type="text"
                required
                autocomplete="username"
                placeholder="usuario"
                input:class="text-zinc-950! placeholder:text-zinc-600!"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                autocomplete="email"
                placeholder="email@example.com"
                input:class="text-zinc-950! placeholder:text-zinc-600!"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                input:class="text-zinc-950! placeholder:text-zinc-600!"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                input:class="text-zinc-950! placeholder:text-zinc-600!"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full bg-sky-600! text-white! hover:bg-sky-700!" x-bind:disabled="submitting" data-test="register-user-button">
                    <span x-show="! submitting">{{ __('Create account') }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('Creando cuenta...') }}
                    </span>
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" class="font-medium text-sky-700! hover:text-sky-800!" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
