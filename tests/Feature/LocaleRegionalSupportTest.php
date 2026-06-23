<?php

use App\Models\Supplier;
use App\Models\User;
use App\Support\LocaleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

test('locale configuration exposes regional metadata', function () {
    expect(config('locales.supported.es'))->toMatchArray([
        'name' => 'Español',
        'language' => 'es',
        'country' => 'MX',
        'currency' => 'MXN',
        'timezone' => 'America/Merida',
        'date_format' => 'd/m/Y',
        'datetime_format' => 'd/m/Y H:i',
        'direction' => 'ltr',
    ])->and(config('locales.supported.en'))->toMatchArray([
        'name' => 'English',
        'country' => 'US',
        'currency' => 'USD',
        'timezone' => 'America/New_York',
        'date_format' => 'm/d/Y',
        'datetime_format' => 'm/d/Y h:i A',
    ]);
});

test('locale service uses user timezone before locale timezone', function () {
    $supplier = Supplier::factory()->create([
        'timezone' => 'America/New_York',
    ]);
    $user = User::factory()->create([
        'preferred_locale' => 'en',
        'supplier_id' => $supplier->id,
        'timezone' => 'Europe/Paris',
    ]);

    $this->actingAs($user);
    App::setLocale('en');

    $date = Carbon::parse('2026-06-18 12:00:00', 'UTC');

    expect(app(LocaleService::class)->getTimezone())->toBe('Europe/Paris')
        ->and(format_datetime($date))->toBe('06/18/2026 02:00 PM');
});

test('date formatting preserves date values without timezone shifting', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'en',
        'timezone' => 'America/New_York',
    ]);

    $this->actingAs($user);
    App::setLocale('en');

    $date = Carbon::parse('2026-07-01 00:00:00', 'UTC');

    expect(format_date($date))->toBe('07/01/2026')
        ->and(format_datetime($date))->toBe('06/30/2026 08:00 PM');
});

test('locale service falls back to locale timezone when user timezone is empty', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'pt',
        'timezone' => null,
    ]);

    $this->actingAs($user);
    App::setLocale('pt');

    expect(app(LocaleService::class)->getTimezone())->toBe('America/Sao_Paulo');
});

test('locale service uses supplier timezone before locale timezone', function () {
    $supplier = Supplier::factory()->create([
        'timezone' => 'America/New_York',
    ]);
    $user = User::factory()->create([
        'preferred_locale' => 'pt',
        'supplier_id' => $supplier->id,
        'timezone' => null,
    ]);

    $this->actingAs($user);
    App::setLocale('pt');

    expect(app(LocaleService::class)->getTimezone())->toBe('America/New_York');
});

test('money formatting respects explicit business currency', function () {
    App::setLocale('pt');

    expect(format_money(1500, 'MXN'))->toStartWith('MXN ')
        ->not->toStartWith('BRL ');
});

test('money formatting does not infer business currency from locale', function () {
    App::setLocale('pt');

    expect(format_money(1500))->not->toStartWith('BRL ')
        ->and(format_money(1500))->not->toStartWith('MXN ')
        ->and(format_money(1500, 'EUR'))->toStartWith('EUR ');
});

test('profile timezone can be updated and validated', function () {
    $user = User::factory()->create([
        'timezone' => 'America/Merida',
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('timezone', 'America/New_York')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->timezone)->toBe('America/New_York');

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('timezone', 'Invalid/Timezone')
        ->call('updateProfileInformation')
        ->assertHasErrors(['timezone']);
});
