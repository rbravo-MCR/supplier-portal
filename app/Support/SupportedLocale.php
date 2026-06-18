<?php

namespace App\Support;

class SupportedLocale
{
    /**
     * @var array<string, array<string, string>>|null
     */
    private static ?array $supportedLocales = null;

    private static ?string $defaultLocale = null;

    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        return self::$supportedLocales ??= app(LocaleService::class)->getSupportedLocales();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::all())
            ->mapWithKeys(fn (array $locale, string $code): array => [$code => $locale['name'] ?? $code])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::options());
    }

    public static function default(): string
    {
        return self::$defaultLocale ??= app(LocaleService::class)->getDefaultLocale();
    }

    public static function isSupported(?string $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, self::all());
    }

    public static function normalize(?string $locale): string
    {
        return self::isSupported($locale)
            ? $locale
            : self::default();
    }

    public static function label(?string $locale): string
    {
        $normalizedLocale = self::normalize($locale);

        return self::options()[$normalizedLocale] ?? $normalizedLocale;
    }
}
