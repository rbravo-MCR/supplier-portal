@props([
    'sidebar' => false,
])

@if($sidebar)
    <a {{ $attributes->merge(['class' => 'flex w-full flex-col items-center justify-center gap-2 py-3 text-center']) }}>
        <x-app-logo-icon class="h-16 max-h-16 w-44 max-w-full" />

        <span class="text-sm font-semibold leading-tight text-zinc-900 dark:text-white">
            {{ __('Supplier portal') }}
        </span>
    </a>
@else
    <flux:brand :name="__('Supplier portal')" {{ $attributes }}>
        <x-slot name="logo" class="flex h-10 w-40 items-center justify-center">
            <x-app-logo-icon class="max-h-10 w-40" />
        </x-slot>
    </flux:brand>
@endif
