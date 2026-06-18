<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class I18nAuditService
{
    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $locales = SupportedLocale::codes();
        $defaultLocale = SupportedLocale::default();
        $translations = $this->translations($locales);
        $usedKeys = $this->usedTranslationKeys();
        $hardcoded = $this->hardcodedBladeText();
        $defaultKeys = array_values(array_unique([
            ...array_keys($translations[$defaultLocale] ?? []),
            ...$usedKeys,
        ]));

        sort($defaultKeys);

        $missing = [];
        $coverage = [];

        foreach ($locales as $locale) {
            $localeKeys = array_keys($translations[$locale] ?? []);
            $missing[$locale] = array_values(array_diff($defaultKeys, $localeKeys));
            $coverage[$locale] = [
                'defined' => count($localeKeys),
                'required' => count($defaultKeys),
                'percent' => $defaultKeys === [] ? 100.0 : round(((count($defaultKeys) - count($missing[$locale])) / count($defaultKeys)) * 100, 2),
            ];
        }

        $definedKeys = collect($translations)
            ->flatMap(fn (array $keys): array => array_keys($keys))
            ->unique()
            ->values()
            ->all();

        $unused = array_values(array_diff($definedKeys, $usedKeys));
        sort($unused);

        $undefinedCalls = array_values(array_diff($usedKeys, $definedKeys));
        sort($undefinedCalls);

        return [
            'locales' => $locales,
            'default_locale' => $defaultLocale,
            'translations' => $translations,
            'used_keys' => $usedKeys,
            'default_keys' => $defaultKeys,
            'missing' => $missing,
            'unused' => $unused,
            'undefined_calls' => $undefinedCalls,
            'hardcoded' => $hardcoded,
            'coverage' => $coverage,
            'module_coverage' => $this->moduleCoverage($usedKeys, $translations[$defaultLocale] ?? [], $hardcoded),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function translations(?array $locales = null): array
    {
        $locales ??= SupportedLocale::codes();

        return collect($locales)
            ->mapWithKeys(fn (string $locale): array => [$locale => $this->translationKeys($locale)])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function missingForLocale(string $locale): array
    {
        $audit = $this->audit();

        return $audit['missing'][$locale] ?? [];
    }

    public function writeMissing(string $locale, string $pendingPrefix = '[PENDING TRANSLATION]'): int
    {
        $defaultLocale = SupportedLocale::default();
        $translations = $this->translations([$defaultLocale, $locale]);
        $missing = $this->missingForLocale($locale);

        if ($missing === []) {
            return 0;
        }

        $target = $translations[$locale] ?? [];
        $source = $translations[$defaultLocale] ?? [];

        foreach ($missing as $key) {
            $target[$key] = $locale === $defaultLocale
                ? $key
                : trim("{$pendingPrefix} ".$this->withoutPendingPrefix($source[$key] ?? $key, $pendingPrefix));
        }

        ksort($target);
        File::ensureDirectoryExists(lang_path());
        File::put(
            $this->jsonPath($locale),
            json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return count($missing);
    }

    public function normalizePendingValues(string $locale, string $pendingPrefix = '[PENDING TRANSLATION]'): int
    {
        $path = $this->jsonPath($locale);

        if (! File::exists($path)) {
            return 0;
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            return 0;
        }

        $defaultLocale = SupportedLocale::default();
        $changed = 0;

        foreach ($decoded as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $normalized = $locale === $defaultLocale || ! str_starts_with($value, $pendingPrefix)
                ? $this->withoutPendingPrefix($value, $pendingPrefix)
                : $this->pendingValue($this->withoutPendingPrefix($value, $pendingPrefix), $pendingPrefix);

            if ($normalized !== $value) {
                $decoded[$key] = $normalized;
                $changed++;
            }
        }

        if ($changed > 0) {
            ksort($decoded);
            File::put(
                $path,
                json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
            );
        }

        return $changed;
    }

    /**
     * @return array<string, string>
     */
    private function translationKeys(string $locale): array
    {
        $keys = [];
        $jsonPath = $this->jsonPath($locale);

        if (File::exists($jsonPath)) {
            $decoded = json_decode(File::get($jsonPath), true);

            if (is_array($decoded)) {
                $keys = array_merge($keys, Arr::where($decoded, fn (mixed $value): bool => is_string($value)));
            }
        }

        $phpPath = lang_path($locale);

        if (File::isDirectory($phpPath)) {
            foreach (File::allFiles($phpPath) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $group = $file->getBasename('.php');
                $lines = require $file->getPathname();

                if (is_array($lines)) {
                    foreach (Arr::dot($lines) as $key => $value) {
                        if (is_string($value)) {
                            $keys["{$group}.{$key}"] = $value;
                        }
                    }
                }
            }
        }

        ksort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function usedTranslationKeys(): array
    {
        $keys = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = File::get($file->getPathname());
            preg_match_all('/(?:__|trans|@lang)\(\s*([\'"])(.*?)\1/s', $contents, $matches);
            preg_match_all('/trans_choice\(\s*([\'"])(.*?)\1/s', $contents, $choiceMatches);

            foreach ([...($matches[2] ?? []), ...($choiceMatches[2] ?? [])] as $key) {
                if ($key !== '' && ! Str::contains($key, ['{', '$'])) {
                    $keys[] = stripcslashes($key);
                }
            }
        }

        foreach (config('imports.pricing_template.columns', []) as $column) {
            $key = $column['translation_key'] ?? null;

            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * @return list<array{file: string, line: int, text: string, module: string}>
     */
    private function hardcodedBladeText(): array
    {
        $issues = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = File::get($file->getPathname());
            $contents = preg_replace('/<\?(?:php|=).*?\?>/s', '', $contents) ?? $contents;
            $contents = preg_replace('/@php\b.*?@endphp/s', '', $contents) ?? $contents;
            $contents = preg_replace('/{{--.*?--}}/s', '', $contents) ?? $contents;
            $contents = preg_replace('/<script\b.*?<\/script>/is', '', $contents) ?? $contents;
            $contents = preg_replace('/<style\b.*?<\/style>/is', '', $contents) ?? $contents;
            $contents = preg_replace('/<svg\b.*?<\/svg>/is', '', $contents) ?? $contents;
            $contents = preg_replace('/^\s*@[\w:.]+(?:\s*\(.*?\))?\s*$/m', '', $contents) ?? $contents;
            $contents = preg_replace('/@[a-zA-Z_][\w:.]*(?:\s*\([^)]*\))?/s', '', $contents) ?? $contents;

            preg_match_all('/>([^<>{}@][^<>{}]*)</u', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] ?? [] as [$text, $offset]) {
                $normalized = trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

                if (! $this->isVisibleHardcodedText($normalized)) {
                    continue;
                }

                $issues[] = [
                    'file' => str_replace(base_path(DIRECTORY_SEPARATOR), '', $file->getPathname()),
                    'line' => substr_count(substr($contents, 0, $offset), "\n") + 1,
                    'text' => $normalized,
                    'module' => $this->moduleName($file->getPathname()),
                ];
            }
        }

        return $issues;
    }

    private function isVisibleHardcodedText(string $text): bool
    {
        if ($text === '' || mb_strlen($text) < 2) {
            return false;
        }

        if (preg_match('/^[\d\s[:punct:]]+$/u', $text) === 1) {
            return false;
        }

        if (Str::contains($text, ['{{', '}}', '$', '::', 'wire:', 'http://', 'https://'])) {
            return false;
        }

        if (str_starts_with($text, '@') || str_starts_with($text, ':')) {
            return false;
        }

        return preg_match('/\pL/u', $text) === 1;
    }

    /**
     * @param  list<string>  $usedKeys
     * @param  array<string, string>  $defaultTranslations
     * @param  list<array{file: string, line: int, text: string, module: string}>  $hardcoded
     * @return array<string, array{used: int, translated: int, hardcoded: int, percent: float}>
     */
    private function moduleCoverage(array $usedKeys, array $defaultTranslations, array $hardcoded): array
    {
        $modules = [];

        foreach ($this->sourceFiles() as $file) {
            $module = $this->moduleName($file->getPathname());
            $contents = File::get($file->getPathname());
            preg_match_all('/(?:__|trans|@lang)\(\s*([\'"])(.*?)\1/s', $contents, $matches);
            preg_match_all('/trans_choice\(\s*([\'"])(.*?)\1/s', $contents, $choiceMatches);
            $keys = array_values(array_unique([...($matches[2] ?? []), ...($choiceMatches[2] ?? [])]));

            $modules[$module] ??= ['used' => 0, 'translated' => 0, 'hardcoded' => 0, 'percent' => 100.0];
            $modules[$module]['used'] += count($keys);
            $modules[$module]['translated'] += count(array_intersect($keys, array_keys($defaultTranslations)));
        }

        foreach ($hardcoded as $issue) {
            $modules[$issue['module']] ??= ['used' => 0, 'translated' => 0, 'hardcoded' => 0, 'percent' => 100.0];
            $modules[$issue['module']]['hardcoded']++;
        }

        foreach ($modules as $module => $data) {
            $required = $data['used'] + $data['hardcoded'];
            $modules[$module]['percent'] = $required === 0 ? 100.0 : round(($data['translated'] / $required) * 100, 2);
        }

        ksort($modules);

        return $modules;
    }

    /**
     * @return SplFileInfo[]
     */
    private function sourceFiles(): array
    {
        $roots = [
            resource_path('views'),
            app_path(),
            base_path('routes'),
        ];

        $files = [];

        foreach ($roots as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if (in_array($file->getExtension(), ['php'], true)) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function moduleName(string $path): string
    {
        $relative = str_replace([resource_path('views').DIRECTORY_SEPARATOR, base_path(DIRECTORY_SEPARATOR)], '', $path);
        $relative = str_replace('\\', '/', $relative);

        if (str_starts_with($relative, 'pages/')) {
            return explode('/', str_replace('pages/', '', $relative))[0] ?: 'pages';
        }

        if (str_starts_with($relative, 'components/')) {
            return 'components';
        }

        if (str_starts_with($relative, 'layouts/')) {
            return 'layouts';
        }

        if (str_starts_with($relative, 'app/')) {
            return 'app';
        }

        if (str_starts_with($relative, 'routes/')) {
            return 'routes';
        }

        return str($relative)->before('/')->before('.')->toString() ?: 'root';
    }

    private function jsonPath(string $locale): string
    {
        return lang_path("{$locale}.json");
    }

    private function pendingValue(string $value, string $pendingPrefix): string
    {
        return trim("{$pendingPrefix} {$value}");
    }

    private function withoutPendingPrefix(string $value, string $pendingPrefix): string
    {
        $normalized = trim($value);

        while (str_starts_with($normalized, $pendingPrefix)) {
            $normalized = trim(Str::after($normalized, $pendingPrefix));
        }

        return $normalized;
    }
}
