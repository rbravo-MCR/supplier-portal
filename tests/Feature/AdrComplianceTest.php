<?php

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\User;
use App\Modules\Booking\Application\UseCases\ListPendingBookings;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;

test('application code does not use model all in production paths', function () {
    $violations = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->filter(function (SplFileInfo $file) {
            $contents = File::get($file->getPathname());

            return preg_match('/::\s*all\s*\(/', $contents) === 1;
        })
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values();

    expect($violations)->toBeEmpty();
});

test('booking queue is paginated for adr 009', function () {
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create([
        'role' => 'supplier_user',
        'supplier_id' => $supplier->id,
    ]);

    Booking::factory()->count(3)->create([
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $bookings = app(ListPendingBookings::class)->handle(perPage: 2);

    expect($bookings)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($bookings->total())->toBe(3)
        ->and($bookings->perPage())->toBe(2);
});
