<?php

use App\Shared\Support\IncidentId;
use Illuminate\Support\Facades\Route;

test('session defaults mitigate session theft', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('SESSION_DRIVER=database')
        ->and(file_get_contents(base_path('.env.example')))->toContain('APP_ENV=production')
        ->and(file_get_contents(base_path('.env.example')))->toContain('APP_DEBUG=false')
        ->and(file_get_contents(base_path('.env.example')))->toContain('POSTMARK_API_KEY=')
        ->and(file_get_contents(base_path('.env.example')))->toContain('RESEND_API_KEY=')
        ->and(file_get_contents(base_path('.env.example')))->toContain('SLACK_BOT_USER_OAUTH_TOKEN=')
        ->and(file_get_contents(base_path('.env.example')))->toContain('SLACK_BOT_USER_DEFAULT_CHANNEL=')
        ->and(file_get_contents(base_path('.env.example')))->toContain('SUPPLIER_SERVICE_TOKEN=')
        ->and(config('session.encrypt'))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

test('production errors return safe message with incident id and no technical details', function () {
    config(['app.debug' => false]);

    Route::get('/threat-model/explodes', function () {
        throw new RuntimeException('SQLSTATE[08006]: connection failed at /internal/path');
    });

    $this->get('/threat-model/explodes')
        ->assertStatus(503)
        ->assertSee('Estamos teniendo una intermitencia temporal.')
        ->assertSee('Código de seguimiento:')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('/internal/path')
        ->assertDontSee('RuntimeException');
});

test('production api errors return 503 json with incident id and no technical details', function () {
    config(['app.debug' => false]);

    Route::post('/api/threat-model/explodes', function () {
        throw new RuntimeException('SQLSTATE[08006]: connection failed at /internal/path');
    });

    $this->postJson('/api/threat-model/explodes')
        ->assertStatus(503)
        ->assertJsonStructure(['message', 'incident_id'])
        ->assertJsonMissing(['exception'])
        ->assertSee('Estamos teniendo una intermitencia temporal.');
});

test('incident ids are generated with expected public prefix', function () {
    expect(IncidentId::generate())->toStartWith('INC-');
});
