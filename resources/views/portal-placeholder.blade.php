<x-layouts::app :title="$title">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ $title }}</flux:heading>
            <flux:text class="max-w-3xl">{{ $description }}</flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Vista') }}</flux:heading>
                <flux:text>{{ __('Pendiente de conectar con el módulo correspondiente.') }}</flux:text>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Datos') }}</flux:heading>
                <flux:text>{{ __('La capa de dominio ya existe para algunos módulos; falta exponer la UI.') }}</flux:text>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading>{{ __('Acciones') }}</flux:heading>
                <flux:text>{{ __('Los permisos y flujos se deben cerrar por rol antes del CRUD final.') }}</flux:text>
            </div>
        </div>
    </div>
</x-layouts::app>
