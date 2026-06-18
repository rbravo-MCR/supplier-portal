<?php

namespace App\Console\Commands;

use App\Support\I18nAuditService;
use App\Support\SupportedLocale;
use Illuminate\Console\Command;

class I18nMissingCommand extends Command
{
    protected $signature = 'i18n:missing
        {locale? : Locale to check. Defaults to all non-default locales.}
        {--write : Write missing keys with pending translation values}
        {--normalize-pending : Remove duplicate pending prefixes and keep the default locale as source text}
        {--pending-prefix=[PENDING TRANSLATION] : Prefix used when --write creates missing keys}
        {--no-fail : Always exit successfully}';

    protected $description = 'Compare lang/es against supported locales and list or write missing translation keys.';

    public function handle(I18nAuditService $auditService): int
    {
        $defaultLocale = SupportedLocale::default();
        $requestedLocale = $this->argument('locale');
        $locales = $requestedLocale
            ? [(string) $requestedLocale]
            : array_values(array_diff(SupportedLocale::codes(), [$defaultLocale]));

        $hasMissing = false;

        foreach ($locales as $locale) {
            if (! in_array($locale, SupportedLocale::codes(), true)) {
                $this->error("Unsupported locale: {$locale}");

                return $this->option('no-fail') ? self::SUCCESS : self::FAILURE;
            }

            if ($locale === $defaultLocale && ! $this->option('write') && ! $this->option('normalize-pending')) {
                $this->info("{$locale}: default locale, skipped");

                continue;
            }

            if ($this->option('normalize-pending')) {
                $changed = $auditService->normalizePendingValues($locale, (string) $this->option('pending-prefix'));
                $this->info("{$locale}: {$changed} pending values normalized");
            }

            $missing = $auditService->missingForLocale($locale);
            $hasMissing = $hasMissing || $missing !== [];

            if ($this->option('write')) {
                $written = $auditService->writeMissing($locale, (string) $this->option('pending-prefix'));
                $this->info("{$locale}: {$written} missing keys written");

                continue;
            }

            $missing === []
                ? $this->info("{$locale}: no missing keys")
                : $this->warn("{$locale}: ".count($missing).' missing keys');

            foreach ($missing as $key) {
                $this->line(" - {$key}");
            }
        }

        return $hasMissing && ! $this->option('write') && ! $this->option('no-fail')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
