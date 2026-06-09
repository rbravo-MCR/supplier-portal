<?php

use Illuminate\Support\Facades\File;

test('active modules follow the official directory blueprint', function () {
    $modules = ['Supplier', 'Identity', 'Booking', 'Pricing', 'Import'];
    $requiredDirectories = [
        'Domain',
        'Domain/Entities',
        'Domain/ValueObjects',
        'Domain/Rules',
        'Domain/Events',
        'Domain/Exceptions',
        'Application',
        'Application/UseCases',
        'Application/DTOs',
        'Application/Services',
        'Infrastructure',
        'Infrastructure/Repositories',
        'Infrastructure/Persistence',
        'Infrastructure/External',
        'Presentation',
        'Presentation/Filament',
        'Policies',
        'Jobs',
        'Tests',
    ];

    foreach ($modules as $module) {
        foreach ($requiredDirectories as $directory) {
            expect(File::isDirectory(app_path("Modules/{$module}/{$directory}")))
                ->toBeTrue("Missing {$module}/{$directory}");
        }
    }
});

test('domain layer does not depend on infrastructure or presentation concerns', function () {
    $forbiddenImports = [
        'Illuminate\\Database\\Eloquent',
        'Illuminate\\Support\\Facades\\DB',
        'Filament\\',
        'Illuminate\\Http',
        'Illuminate\\Support\\Facades\\Redis',
    ];

    $violations = collect(File::allFiles(app_path('Modules')))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->flatMap(function (SplFileInfo $file) use ($forbiddenImports) {
            $contents = File::get($file->getPathname());

            return collect($forbiddenImports)
                ->filter(fn (string $import) => str_contains($contents, $import))
                ->map(fn (string $import) => "{$file->getRelativePathname()} imports {$import}");
        })
        ->values();

    expect($violations)->toBeEmpty();
});
