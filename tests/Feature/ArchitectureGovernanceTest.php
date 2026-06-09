<?php

use Illuminate\Support\Facades\File;

test('outbox event types used by code are documented in event catalog', function () {
    $eventCatalog = File::get(base_path('docs/08_EVENT_CATALOG.md'));

    $usedEvents = collect(File::allFiles(app_path('Modules')))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->flatMap(function (SplFileInfo $file) {
            preg_match_all("/'event_type'\\s*=>\\s*'([^']+)'/", File::get($file->getPathname()), $matches);

            return $matches[1] ?? [];
        })
        ->unique()
        ->values();

    expect($usedEvents)->not->toBeEmpty();

    foreach ($usedEvents as $eventType) {
        expect($eventCatalog)->toContain("### {$eventType}");
    }
});

test('portal migrations define timestamps and public uuid for business tables', function () {
    $tablesRequiringUuid = [
        'suppliers',
        'bookings',
        'booking_actions',
        'outbox_events',
        'rates',
        'rate_imports',
        'audit_logs',
    ];

    foreach ($tablesRequiringUuid as $table) {
        $migration = migrationForTable($table);

        expect($migration)->not->toBeNull("Missing migration for {$table}")
            ->and($migration)->toContain("Schema::create('{$table}'")
            ->and($migration)->toContain('->uuid(')
            ->and($migration)->toContain('->timestamps');
    }
});

test('supplier owned business tables define supplier owner and indexes', function () {
    $supplierOwnedTables = [
        'bookings',
        'booking_actions',
        'rates',
        'rate_imports',
    ];

    foreach ($supplierOwnedTables as $table) {
        $migration = migrationForTable($table);

        expect($migration)->not->toBeNull("Missing migration for {$table}")
            ->and($migration)->toContain("foreignId('supplier_id')")
            ->and($migration)->toContain('supplier_id');
    }
});

function migrationForTable(string $table): ?string
{
    foreach (File::files(database_path('migrations')) as $file) {
        $contents = File::get($file->getPathname());

        if (str_contains($contents, "Schema::create('{$table}'")) {
            return $contents;
        }
    }

    return null;
}
