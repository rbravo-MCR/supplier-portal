@php
    $status = $health['status'] ?? 'degraded';
    $checks = $health['checks'] ?? [];

    $badgeColors = [
        'healthy' => 'green',
        'degraded' => 'amber',
        'down' => 'red',
    ];

    $labels = [
        'db' => __('Base de datos'),
        'redis' => __('Redis'),
        'queue' => __('Cola'),
        'storage' => __('Storage'),
        'outbox' => __('Outbox'),
        'failed_jobs' => __('Jobs fallidos'),
    ];
@endphp

<x-layouts::app :title="__('Estado')">
    <section class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Estado') }}</flux:heading>
            <flux:text class="max-w-3xl">
                {{ __('Consulta salud del sistema, cola, storage, outbox y jobs fallidos.') }}
            </flux:text>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading>{{ __('Resumen operativo') }}</flux:heading>
                    <flux:text>{{ __('Estado agregado calculado desde los checks de recuperación.') }}</flux:text>
                </div>

                <flux:badge :color="$badgeColors[$status] ?? 'zinc'" data-test="system-status">
                    {{ __(str($status)->replace('_', ' ')->title()->toString()) }}
                </flux:badge>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Componente') }}</flux:table.column>
                    <flux:table.column>{{ __('Estado') }}</flux:table.column>
                    <flux:table.column>{{ __('Detalle') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($checks as $key => $check)
                        @php
                            $componentStatus = $check['status'] ?? 'degraded';
                            $details = collect($check)
                                ->except(['status'])
                                ->map(fn ($value, $name) => str($name)->replace('_', ' ')->title().': '.(is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? '-')))
                                ->implode(' · ');
                        @endphp

                        <flux:table.row>
                            <flux:table.cell>{{ $labels[$key] ?? str($key)->replace('_', ' ')->title() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$badgeColors[$componentStatus] ?? 'zinc'">
                                    {{ __(str($componentStatus)->replace('_', ' ')->title()->toString()) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $details !== '' ? $details : __('Sin incidencias') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </section>
</x-layouts::app>
