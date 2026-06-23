@props([
    'dataTest' => 'locale-select',
    'showLabel' => true,
])

@php
    $locales = \App\Support\SupportedLocale::menuOptions();
    $currentLocale = \App\Support\SupportedLocale::normalize(auth()->user()?->preferred_locale ?? session('locale'));
    $selectorTest = $attributes->get('data-test', $dataTest);
    $currentFlagStyle = match ($currentLocale) {
        'en' => 'background: linear-gradient(#b22234 0 7.69%, #fff 7.69% 15.38%, #b22234 15.38% 23.07%, #fff 23.07% 30.76%, #b22234 30.76% 38.45%, #fff 38.45% 46.14%, #b22234 46.14% 53.83%, #fff 53.83% 61.52%, #b22234 61.52% 69.21%, #fff 69.21% 76.9%, #b22234 76.9% 84.59%, #fff 84.59% 92.28%, #b22234 92.28% 100%); position: relative;',
        'pt' => 'background: linear-gradient(90deg, #046a38 0 40%, #da291c 40% 100%);',
        'fr' => 'background: linear-gradient(90deg, #0055a4 0 33.33%, #fff 33.33% 66.66%, #ef4135 66.66% 100%);',
        'it' => 'background: linear-gradient(90deg, #009246 0 33.33%, #ffffff 33.33% 66.66%, #ce2b37 66.66% 100%);',
        'zh' => 'background: #de2910; position: relative;',
        'ja' => 'background: #ffffff; position: relative;',
        default => 'background: linear-gradient(#aa151b 0 25%, #f1bf00 25% 75%, #aa151b 75% 100%);',
    };
@endphp

<div {{ $attributes->merge(['class' => 'min-w-0']) }} x-data="{ isChanging: false }">
    <form method="POST" action="{{ route('locale.update') }}">
        @csrf
        <input x-ref="locale" type="hidden" name="locale" value="{{ $currentLocale }}">

        <div class="flex items-end gap-2">
            <span
                class="mb-1 inline-block h-5 w-7 shrink-0 overflow-hidden rounded-sm border border-zinc-300 shadow-sm dark:border-zinc-600"
                style="{{ $currentFlagStyle }}"
                aria-hidden="true"
                data-test="{{ $selectorTest }}-flag"
            >
                @if ($currentLocale === 'en')
                    <span class="block h-[54%] w-[42%] bg-blue-800"></span>
                @elseif ($currentLocale === 'pt')
                    <span class="mx-auto mt-1 block size-3 rounded-full bg-yellow-300"></span>
                @elseif ($currentLocale === 'zh')
                    <span class="absolute left-0.5 top-0.5 block size-1.5 bg-[#ffde00]" style="clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);"></span>
                @elseif ($currentLocale === 'ja')
                    <span class="absolute inset-0 m-auto block size-3 rounded-full bg-[#bc002d]"></span>
                @endif
            </span>

            <div class="min-w-0 flex-1">
                @if ($showLabel)
                    <flux:select
                        :label="__('Idioma')"
                        size="sm"
                        x-on:change="$refs.locale.value = $event.target.value; isChanging = true; $event.target.disabled = true; $event.target.form.submit()"
                        x-bind:disabled="isChanging"
                        :data-test="$selectorTest"
                    >
                        @foreach ($locales as $localeCode => $localeLabel)
                            <flux:select.option :value="$localeCode" :selected="$currentLocale === $localeCode">
                                {{ $localeLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:select
                        :aria-label="__('Idioma')"
                        size="sm"
                        x-on:change="$refs.locale.value = $event.target.value; isChanging = true; $event.target.disabled = true; $event.target.form.submit()"
                        x-bind:disabled="isChanging"
                        :data-test="$selectorTest"
                    >
                        @foreach ($locales as $localeCode => $localeLabel)
                            <flux:select.option :value="$localeCode" :selected="$currentLocale === $localeCode">
                                {{ $localeLabel }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <svg
                x-show="isChanging"
                x-cloak
                class="mb-2 size-5 shrink-0 animate-spin text-zinc-500 dark:text-zinc-300"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                aria-label="{{ __('Cargando idioma') }}"
                role="status"
                data-test="{{ $selectorTest }}-spinner"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
        </div>
    </form>
</div>
