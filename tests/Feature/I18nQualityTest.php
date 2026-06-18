<?php

test('i18n audit passes without missing keys or visible hardcoded text', function () {
    $this->artisan('i18n:audit')
        ->assertSuccessful();
});

test('i18n missing command confirms supported locales are complete', function () {
    $this->artisan('i18n:missing')
        ->expectsOutput('en: no missing keys')
        ->expectsOutput('pt: no missing keys')
        ->expectsOutput('fr: no missing keys')
        ->assertSuccessful();
});

test('i18n missing command rejects unsupported locales', function () {
    $this->artisan('i18n:missing', ['locale' => 'de'])
        ->expectsOutput('Unsupported locale: de')
        ->assertFailed();
});
