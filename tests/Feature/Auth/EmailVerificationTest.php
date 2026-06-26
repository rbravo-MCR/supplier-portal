<?php

use Illuminate\Support\Facades\Route;

test('email verification routes are enabled', function () {
    expect(Route::has('verification.notice'))->toBeTrue()
        ->and(Route::has('verification.verify'))->toBeTrue()
        ->and(Route::has('verification.send'))->toBeTrue();
});
