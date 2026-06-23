<?php

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

test('users table stores a preferred locale', function () {
    expect(Schema::hasColumn('users', 'preferred_locale'))->toBeTrue();
});

test('registration rejects unsupported preferred locales', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->post(route('register.store'), [
        'supplier_id' => $supplier->id,
        'name' => 'Jean Martin',
        'username' => 'jmartin',
        'email' => 'jean@example.com',
        'preferred_locale' => 'de',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('preferred_locale');
    $this->assertGuest();
});

test('registration requires a preferred locale field', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->post(route('register.store'), [
        'supplier_id' => $supplier->id,
        'name' => 'Jean Martin',
        'username' => 'jmartin',
        'email' => 'jean@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('preferred_locale');
    $this->assertGuest();
});

test('login stores the authenticated user preferred locale in session', function () {
    $user = User::factory()->create([
        'username' => 'localeuser',
        'preferred_locale' => 'fr',
    ]);

    $response = $this->post(route('login.store'), [
        'username' => 'localeuser',
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('locale', 'fr');

    $this->assertAuthenticatedAs($user);
    expect(App::currentLocale())->toBe('fr');
});

test('authenticated navigation applies locale from database over existing session', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'preferred_locale' => 'en',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'pt'])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSessionHas('locale', 'en');

    expect(App::currentLocale())->toBe('en');
});

test('authenticated users see the portal in their preferred locale', function (string $locale, string $expectedText) {
    $user = User::factory()->create([
        'role' => 'admin',
        'preferred_locale' => $locale,
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee($expectedText)
        ->assertSessionHas('locale', $locale);

    expect(App::currentLocale())->toBe($locale);
})->with([
    'es' => ['es', 'Resumen por estado de reservas con filtro por fecha de reserva.'],
    'en' => ['en', 'Booking status summary filtered by reservation date.'],
    'pt' => ['pt', 'Resumo de reservas por status com filtro por data da reserva.'],
    'fr' => ['fr', 'Résumé des réservations par statut avec filtre par date de réservation.'],
]);

test('authenticated users see locale menu options with flags', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'preferred_locale' => 'es',
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-test="sidebar-locale-select"', false)
        ->assertSee('data-test="sidebar-locale-select-flag"', false)
        ->assertSee('data-test="sidebar-locale-select-spinner"', false)
        ->assertSee('data-test="mobile-header-locale-select"', false)
        ->assertSee('data-test="mobile-header-locale-select-flag"', false)
        ->assertSee('data-test="mobile-header-locale-select-spinner"', false)
        ->assertSee('Español')
        ->assertSee('Inglés')
        ->assertSee('Portugués')
        ->assertSee('Francés');
});

test('empty preferred locale falls back to spanish', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'preferred_locale' => '',
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'fr'])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Resumen por estado de reservas con filtro por fecha de reserva.')
        ->assertSessionHas('locale', 'es');

    expect(App::currentLocale())->toBe('es');
});

test('users can update preferred locale from the menu route', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'es',
    ]);

    $this->actingAs($user)
        ->from(route('admin.dashboard'))
        ->post(route('locale.update'), [
            'locale' => 'pt',
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('locale', 'pt');

    expect($user->refresh()->preferred_locale)->toBe('pt')
        ->and(App::currentLocale())->toBe('pt');
});

test('locale update route uses settings path and requires authentication', function () {
    $this->post('/settings/locale', [
        'locale' => 'en',
    ])->assertRedirect(route('login'));
});

test('users can only update their own preferred locale', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'es',
    ]);
    $otherUser = User::factory()->create([
        'preferred_locale' => 'en',
    ]);

    $this->actingAs($user)
        ->from(route('admin.dashboard'))
        ->post(route('locale.update'), [
            'locale' => 'fr',
            'user_id' => $otherUser->id,
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('locale', 'fr');

    expect($user->refresh()->preferred_locale)->toBe('fr')
        ->and($otherUser->refresh()->preferred_locale)->toBe('en');
});

test('preferred locale changes are logged', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'es',
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('User preferred locale changed', Mockery::on(function (array $context) use ($user): bool {
            return $context['user_id'] === $user->id
                && $context['old_locale'] === 'es'
                && $context['new_locale'] === 'en'
                && filled($context['ip'])
                && array_key_exists('user_agent', $context)
                && filled($context['changed_at']);
        }));

    $this->actingAs($user)
        ->withHeader('User-Agent', 'i18n-test-agent')
        ->from(route('admin.dashboard'))
        ->post(route('locale.update'), [
            'locale' => 'en',
        ])
        ->assertRedirect(route('admin.dashboard'));
});

test('locale update route is rate limited', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'es',
    ]);

    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->from(route('admin.dashboard'))
            ->post(route('locale.update'), [
                'locale' => $attempt % 2 === 0 ? 'en' : 'es',
            ])
            ->assertRedirect(route('admin.dashboard'));
    }

    $this->from(route('admin.dashboard'))
        ->post(route('locale.update'), [
            'locale' => 'pt',
        ])
        ->assertTooManyRequests();
});

test('users cannot update preferred locale to unsupported values', function () {
    $user = User::factory()->create([
        'preferred_locale' => 'es',
    ]);

    $this->actingAs($user)
        ->from(route('admin.dashboard'))
        ->post(route('locale.update'), [
            'locale' => 'it',
        ])
        ->assertSessionHasErrors('locale');

    expect($user->refresh()->preferred_locale)->toBe('es');
});
