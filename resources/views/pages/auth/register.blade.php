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
                data-test="register-supplier"
            >
                <flux:select.option value="">{{ __('Selecciona proveedor') }}</flux:select.option>
                @foreach ($suppliers as $supplier)
                    <flux:select.option :value="$supplier->id" :selected="(string) old('supplier_id') === (string) $supplier->id">
                        {{ $supplier->name }} · {{ $supplier->code }}
                    </flux:select.option>
                @endforeach
            </flux:select>

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
            />

            <flux:input
                name="username"
                :label="__('Usuario')"
                :value="old('username')"
                type="text"
                required
                autocomplete="username"
                placeholder="usuario"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
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
