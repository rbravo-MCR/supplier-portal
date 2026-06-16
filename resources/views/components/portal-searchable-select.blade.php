@props([
    'label',
    'property',
    'searchProperty',
    'options',
    'selectedLabel' => null,
    'selectedIso2' => null,
    'placeholder' => 'Selecciona',
    'searchPlaceholder' => 'Buscar',
    'empty' => 'Sin resultados',
    'dataTest' => null,
    'live' => false,
])

<div
    class="relative"
    x-data="{ open: false, selectedLabel: @js($selectedLabel ?: $placeholder), selectedIso2: @js($selectedIso2) }"
    x-on:click.outside="open = false"
>
    <div class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $label }}</div>

    <button
        type="button"
        class="flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-start text-sm text-zinc-900 shadow-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:hover:bg-zinc-800"
        x-on:click="open = ! open"
        @if($dataTest) data-test="{{ $dataTest }}" @endif
    >
        <span class="flex min-w-0 items-center gap-2">
            @if($selectedIso2 !== null)
                <img
                    x-bind:src="selectedIso2 ? `https://flagcdn.com/w20/${selectedIso2.toLowerCase()}.png` : ''"
                    x-bind:srcset="selectedIso2 ? `https://flagcdn.com/w40/${selectedIso2.toLowerCase()}.png 2x` : ''"
                    alt=""
                    class="h-3.5 w-5 shrink-0 rounded-[2px] object-cover"
                    loading="lazy"
                    x-show="selectedIso2"
                >
            @endif
            <span class="truncate" x-text="selectedLabel"></span>
        </span>
        <flux:icon name="chevron-down" class="size-4 shrink-0 text-zinc-400" />
    </button>

    @error($property)
        <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
    @enderror

    <div
        x-cloak
        x-show="open"
        x-transition
        class="absolute z-30 mt-2 w-full rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="relative">
            <flux:icon name="magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
            <input
                type="search"
                wire:model.live.debounce.500ms="{{ $searchProperty }}"
                placeholder="{{ $searchPlaceholder }}"
                class="h-10 w-full rounded-lg border border-zinc-200 bg-white ps-9 pe-3 text-sm text-zinc-900 outline-hidden placeholder:text-zinc-400 focus:border-yellow-400 focus:ring-2 focus:ring-yellow-200 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder:text-zinc-500 dark:focus:border-yellow-500 dark:focus:ring-yellow-500/20"
            >
        </div>

        <div class="mt-2 max-h-64 overflow-y-auto">
            @forelse ($options as $option)
                <button
                    type="button"
                    @if($live)
                        wire:click="$set('{{ $property }}', {{ $option['value'] }})"
                        x-on:click="selectedLabel = @js($option['label']); selectedIso2 = @js($option['iso2'] ?? null); open = false"
                    @else
                        x-on:click="$wire.set('{{ $property }}', {{ $option['value'] }}, false); selectedLabel = @js($option['label']); selectedIso2 = @js($option['iso2'] ?? null); open = false"
                    @endif
                    class="flex w-full items-center rounded-md px-3 py-2 text-start text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    wire:key="{{ $property }}-option-{{ $option['value'] }}"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        @if(! empty($option['iso2']))
                            <img
                                src="https://flagcdn.com/w20/{{ strtolower($option['iso2']) }}.png"
                                srcset="https://flagcdn.com/w40/{{ strtolower($option['iso2']) }}.png 2x"
                                alt=""
                                class="h-3.5 w-5 shrink-0 rounded-[2px] object-cover"
                                loading="lazy"
                            >
                        @endif
                        <span class="truncate">{{ $option['label'] }}</span>
                    </span>
                </button>
            @empty
                <div class="px-3 py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $empty }}
                </div>
            @endforelse
        </div>
    </div>
</div>
