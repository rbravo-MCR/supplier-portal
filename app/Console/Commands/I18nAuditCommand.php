<?php

namespace App\Console\Commands;

use App\Support\I18nAuditService;
use Illuminate\Console\Command;

class I18nAuditCommand extends Command
{
    protected $signature = 'i18n:audit
        {--json : Output the full report as JSON}
        {--no-fail : Always exit successfully}';

    protected $description = 'Audit translation coverage, missing keys, unused keys, unsupported locales, and visible hardcoded Blade text.';

    public function handle(I18nAuditService $auditService): int
    {
        $report = $auditService->audit();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('I18N audit');
            $this->line('Default locale: '.$report['default_locale']);
            $this->newLine();

            $this->line('Coverage by locale');
            $this->table(
                ['Locale', 'Defined', 'Required', 'Coverage'],
                collect($report['coverage'])
                    ->map(fn (array $coverage, string $locale): array => [
                        $locale,
                        $coverage['defined'],
                        $coverage['required'],
                        "{$coverage['percent']}%",
                    ])
                    ->values()
                    ->all(),
            );

            $this->line('Coverage by module');
            $this->table(
                ['Module', 'Used keys', 'Translated', 'Hardcoded', 'Coverage'],
                collect($report['module_coverage'])
                    ->map(fn (array $coverage, string $module): array => [
                        $module,
                        $coverage['used'],
                        $coverage['translated'],
                        $coverage['hardcoded'],
                        "{$coverage['percent']}%",
                    ])
                    ->values()
                    ->all(),
            );

            $this->renderMissing($report['missing']);
            $this->renderList('Translation calls without lang entries', $report['undefined_calls']);
            $this->renderHardcoded($report['hardcoded']);
            $this->renderList('Defined but unused keys', $report['unused']);
        }

        $failed = collect($report['missing'])->flatten()->isNotEmpty()
            || $report['undefined_calls'] !== []
            || $report['hardcoded'] !== []
            || $this->hasUnsupportedConfiguredLocale($report['locales']);

        return $failed && ! $this->option('no-fail') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, list<string>>  $missing
     */
    private function renderMissing(array $missing): void
    {
        foreach ($missing as $locale => $keys) {
            $this->renderList("Missing keys for {$locale}", $keys);
        }
    }

    /**
     * @param  list<string>  $items
     */
    private function renderList(string $title, array $items): void
    {
        $this->newLine();
        $items === []
            ? $this->info("{$title}: none")
            : $this->warn("{$title}: ".count($items));

        foreach (array_slice($items, 0, 50) as $item) {
            $this->line(" - {$item}");
        }

        if (count($items) > 50) {
            $this->line(' - ...');
        }
    }

    /**
     * @param  list<array{file: string, line: int, text: string, module: string}>  $items
     */
    private function renderHardcoded(array $items): void
    {
        $this->newLine();
        $items === []
            ? $this->info('Visible hardcoded Blade text: none')
            : $this->error('Visible hardcoded Blade text: '.count($items));

        foreach (array_slice($items, 0, 50) as $item) {
            $this->line(" - {$item['file']}:{$item['line']} {$item['text']}");
        }

        if (count($items) > 50) {
            $this->line(' - ...');
        }
    }

    /**
     * @param  list<string>  $locales
     */
    private function hasUnsupportedConfiguredLocale(array $locales): bool
    {
        return collect($locales)
            ->diff(['es', 'en', 'pt', 'fr', 'it', 'zh', 'ja'])
            ->isNotEmpty();
    }
}
