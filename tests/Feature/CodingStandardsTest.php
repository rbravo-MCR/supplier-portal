<?php

use Illuminate\Support\Facades\File;

test('environment values are only read from configuration or bootstrap', function () {
    $allowedRoots = [
        base_path('config'),
        base_path('bootstrap'),
    ];

    $paths = [
        app_path(),
        base_path('routes'),
        database_path(),
        base_path('tests'),
    ];

    $violations = collect($paths)
        ->flatMap(fn (string $path) => File::isDirectory($path) ? File::allFiles($path) : [])
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->reject(function (SplFileInfo $file) use ($allowedRoots) {
            return collect($allowedRoots)
                ->contains(fn (string $root) => str_starts_with($file->getPathname(), $root));
        })
        ->filter(fn (SplFileInfo $file) => str_contains(File::get($file->getPathname()), 'env'.'('))
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values();

    expect($violations)->toBeEmpty();
});

test('use case handle methods do not accept raw arrays', function () {
    $violations = collect(File::allFiles(app_path('Modules')))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'UseCases'.DIRECTORY_SEPARATOR))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->filter(function (SplFileInfo $file) {
            $contents = File::get($file->getPathname());

            return preg_match('/function\s+handle\s*\([^)]*array\s+\$/m', $contents) === 1;
        })
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values();

    expect($violations)->toBeEmpty();
});

test('application dtos use data suffix and readonly promoted properties', function () {
    $violations = collect(File::allFiles(app_path('Modules')))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'DTOs'.DIRECTORY_SEPARATOR))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->flatMap(function (SplFileInfo $file) {
            $contents = File::get($file->getPathname());
            $violations = [];

            if (! str_ends_with($file->getBasename('.php'), 'Data')) {
                $violations[] = "{$file->getRelativePathname()} must end with Data";
            }

            if (str_contains($contents, 'public function __construct(') && ! str_contains($contents, 'public readonly')) {
                $violations[] = "{$file->getRelativePathname()} must use readonly promoted properties";
            }

            return $violations;
        })
        ->values();

    expect($violations)->toBeEmpty();
});

test('domain exceptions are semantic runtime exceptions and never generic exception', function () {
    $violations = collect(File::allFiles(app_path('Modules')))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR.'Exceptions'.DIRECTORY_SEPARATOR))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->flatMap(function (SplFileInfo $file) {
            $contents = File::get($file->getPathname());
            $violations = [];

            if (! str_contains($contents, 'extends RuntimeException')) {
                $violations[] = "{$file->getRelativePathname()} must extend RuntimeException";
            }

            if (preg_match('/new\s+\\\\?Exception\s*\(/', $contents) === 1) {
                $violations[] = "{$file->getRelativePathname()} throws generic Exception";
            }

            return $violations;
        })
        ->values();

    expect($violations)->toBeEmpty();
});

test('presentation layer does not contain persistence or integration logic', function () {
    $presentationPath = app_path('Modules');

    $violations = collect(File::allFiles($presentationPath))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Presentation'.DIRECTORY_SEPARATOR))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->filter(function (SplFileInfo $file) {
            $contents = File::get($file->getPathname());

            return str_contains($contents, '::query(')
                || str_contains($contents, 'DB::')
                || str_contains($contents, 'Http::')
                || str_contains($contents, 'Storage::');
        })
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values();

    expect($violations)->toBeEmpty();
});
