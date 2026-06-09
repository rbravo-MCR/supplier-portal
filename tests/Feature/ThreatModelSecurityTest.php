<?php

use App\Shared\Support\IncidentId;
use Illuminate\Support\Facades\Route;

test('session defaults mitigate session theft', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('SESSION_DRIVER=database')
        ->and(config('session.encrypt'))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

test('production errors return safe message with incident id and no technical details', function () {
    config(['app.debug' => false]);

    Route::get('/threat-model/explodes', function () {
        throw new RuntimeException('SQLSTATE[08006]: connection failed at /internal/path');
    });

    $response = $this->get('/threat-model/explodes');

    $response->assertServerError()
        ->assertSee('Estamos teniendo una intermitencia temporal.')
        ->assertSee('Código de seguimiento:')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('/internal/path')
        ->assertDontSee('RuntimeException');
});

test('incident ids are generated with expected public prefix', function () {
    expect(IncidentId::generate())->toStartWith('INC-');
});
