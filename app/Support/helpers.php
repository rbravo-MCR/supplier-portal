<?php

use App\Support\LocaleService;

if (! function_exists('locale_service')) {
    function locale_service(): LocaleService
    {
        return app(LocaleService::class);
    }
}

if (! function_exists('format_date')) {
    function format_date(mixed $date): string
    {
        return locale_service()->formatDate($date);
    }
}

if (! function_exists('format_datetime')) {
    function format_datetime(mixed $date): string
    {
        return locale_service()->formatDateTime($date);
    }
}

if (! function_exists('format_money')) {
    function format_money(mixed $amount, ?string $currency = null, ?int $decimals = null): string
    {
        return locale_service()->formatMoney($amount, $currency, $decimals);
    }
}

if (! function_exists('format_number')) {
    function format_number(mixed $number, int $decimals = 0): string
    {
        return locale_service()->formatNumber($number, $decimals);
    }
}
