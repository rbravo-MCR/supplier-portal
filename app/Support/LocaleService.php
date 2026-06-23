<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class LocaleService
{
    /**
     * @return array<string, array<string, string>>
     */
    public function getSupportedLocales(): array
    {
        return config('locales.supported', []);
    }

    public function getDefaultLocale(): string
    {
        return config('locales.default', 'es');
    }

    public function isSupported(string $locale): bool
    {
        return array_key_exists($locale, $this->getSupportedLocales());
    }

    public function getCurrentLocale(): string
    {
        $locale = App::currentLocale();

        return $this->isSupported($locale) ? $locale : $this->getDefaultLocale();
    }

    public function getDateFormat(?string $locale = null): string
    {
        return $this->localeConfig($locale)['date_format'] ?? 'Y-m-d';
    }

    public function getDateTimeFormat(?string $locale = null): string
    {
        return $this->localeConfig($locale)['datetime_format'] ?? 'Y-m-d H:i';
    }

    public function getCurrency(?string $locale = null): string
    {
        return $this->localeConfig($locale)['currency'] ?? 'USD';
    }

    public function getTimezone(?string $locale = null): string
    {
        $user = Auth::user();
        $userTimezone = $user?->timezone;

        if (is_string($userTimezone) && $this->isValidTimezone($userTimezone)) {
            return $userTimezone;
        }

        $supplierTimezone = $user?->supplier?->timezone;

        if (is_string($supplierTimezone) && $this->isValidTimezone($supplierTimezone)) {
            return $supplierTimezone;
        }

        $localeTimezone = $this->localeConfig($locale)['timezone'] ?? null;

        if (is_string($localeTimezone) && $this->isValidTimezone($localeTimezone)) {
            return $localeTimezone;
        }

        return config('app.timezone', 'UTC');
    }

    public function formatDate(mixed $date): string
    {
        return $this->carbon($date)
            ->format($this->getDateFormat()) ?? '-';
    }

    public function formatDateTime(mixed $date): string
    {
        return $this->carbon($date)
            ?->timezone($this->getTimezone())
            ->format($this->getDateTimeFormat()) ?? '-';
    }

    public function formatMoney(mixed $amount, ?string $currency = null, ?int $decimals = null): string
    {
        $currencyCode = filled($currency) ? $currency : null;
        $number = $this->formatNumber($amount, $decimals ?? 2);

        return $currencyCode === null ? $number : "{$currencyCode} {$number}";
    }

    public function formatNumber(mixed $number, int $decimals = 0): string
    {
        $numeric = is_numeric($number) ? (float) $number : 0.0;
        $locale = str_replace('_', '-', $this->getCurrentLocale());

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            return $formatter->format($numeric) ?: (string) $number;
        }

        return number_format($numeric, $decimals);
    }

    /**
     * @return array<string, string>
     */
    public function localeConfig(?string $locale = null): array
    {
        $locale ??= $this->getCurrentLocale();

        if (! $this->isSupported($locale)) {
            $locale = $this->getDefaultLocale();
        }

        return $this->getSupportedLocales()[$locale] ?? [];
    }

    public function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    private function carbon(mixed $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date;
        }

        return Carbon::parse($date);
    }
}
