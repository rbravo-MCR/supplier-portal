<?php

use Illuminate\Support\Facades\File;

test('active modules have required implementation artifacts', function () {
    $modules = [
        'Supplier' => [
            'Application/UseCases/CreateSupplier.php',
            'Application/DTOs/CreateSupplierData.php',
            'Application/Contracts/SupplierRepository.php',
            'Infrastructure/Repositories/EloquentSupplierRepository.php',
            'Domain/Rules/SupplierUserLimitRule.php',
        ],
        'Booking' => [
            'Application/UseCases/ConfirmBooking.php',
            'Application/UseCases/RejectBooking.php',
            'Application/DTOs/ConfirmBookingData.php',
            'Application/Contracts/BookingRepository.php',
            'Infrastructure/Repositories/EloquentBookingRepository.php',
            'Domain/Exceptions/BookingMustBePending.php',
        ],
        'Pricing' => [
            'Application/UseCases/PublishRate.php',
            'Application/DTOs/PublishRateData.php',
            'Application/Contracts/RateRepository.php',
            'Infrastructure/Repositories/EloquentRateRepository.php',
            'Domain/Rules/PriceMustBeGreaterThanZero.php',
        ],
        'Import' => [
            'Application/UseCases/CreateRateImport.php',
            'Application/DTOs/CreateRateImportData.php',
            'Application/Contracts/RateImportRepository.php',
            'Infrastructure/Repositories/EloquentRateImportRepository.php',
            'Domain/Exceptions/RateImportCannotBeEmpty.php',
        ],
        'Identity' => [
            'Application/UseCases/CanAuthenticateUser.php',
        ],
    ];

    foreach ($modules as $module => $files) {
        foreach ($files as $file) {
            expect(File::exists(app_path("Modules/{$module}/{$file}")))
                ->toBeTrue("Missing {$module}/{$file}");
        }
    }
});

test('supplier scoped modules have multi supplier tests', function () {
    $requiredTests = [
        'SupplierContextTest.php' => ['ignores supplier id provided by the request'],
        'BookingWorkflowTest.php' => ['supplier only sees own pending bookings', 'supplier cannot access another supplier booking'],
        'PricingPublishRateTest.php' => ['authenticated supplier context'],
        'RateImportStagingTest.php' => ['ignores supplier id from request'],
    ];

    foreach ($requiredTests as $file => $phrases) {
        $contents = File::get(base_path("tests/Feature/{$file}"));

        foreach ($phrases as $phrase) {
            expect($contents)->toContain($phrase);
        }
    }
});

test('state changing use cases have audit or outbox coverage', function () {
    $coverage = [
        'SupplierAdministrationTest.php' => ['audit_logs'],
        'BookingWorkflowTest.php' => ['audit_logs', 'outbox_events'],
        'PricingPublishRateTest.php' => ['audit_logs'],
        'EventCatalogTest.php' => ['BookingConfirmed', 'BookingRejected', 'RateImportUploaded', 'RatesPublished'],
    ];

    foreach ($coverage as $file => $phrases) {
        $contents = File::get(base_path("tests/Feature/{$file}"));

        foreach ($phrases as $phrase) {
            expect($contents)->toContain($phrase);
        }
    }
});

test('definition of done quality gates are represented by automated tests', function () {
    $requiredQualityTests = [
        'AdrComplianceTest.php',
        'CodingStandardsTest.php',
        'ModuleBlueprintTest.php',
        'PermissionMatrixTest.php',
        'QueryBudgetTest.php',
        'ThreatModelSecurityTest.php',
    ];

    foreach ($requiredQualityTests as $file) {
        expect(File::exists(base_path("tests/Feature/{$file}")))->toBeTrue();
    }
});
