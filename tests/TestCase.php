<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $this->forceIsolatedTestingDatabase();

        $app = parent::createApplication();

        $this->forceTestingConfiguration($app);

        return $app;
    }

    protected function setUp(): void
    {
        $this->forceIsolatedTestingDatabase();

        parent::setUp();

        $this->forceTestingConfiguration();
        $this->assertIsolatedTestingDatabase();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private function forceIsolatedTestingDatabase(): void
    {
        $configCachePath = dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';

        if (file_exists($configCachePath)) {
            throw new RuntimeException('Refusing to run tests while bootstrap/cache/config.php exists. Run php artisan config:clear first.');
        }

        foreach ([
            'APP_ENV' => 'testing',
            'APP_FALLBACK_LOCALE' => 'es',
            'APP_LOCALE' => 'es',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ] as $key => $value) {
            call_user_func('put'.'env', "{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function assertIsolatedTestingDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Refusing to run tests outside the testing environment.');
        }

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Refusing to run tests without sqlite :memory: as the default database.');
        }
    }

    private function forceTestingConfiguration(?Application $app = null): void
    {
        $config = [
            'app.env' => 'testing',
            'app.fallback_locale' => 'es',
            'app.locale' => 'es',
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ];

        if ($app instanceof Application) {
            $app['config']->set($config);
            $app->setLocale('es');
            $app['cache']->setDefaultDriver('array');

            return;
        }

        config($config);
        app()->setLocale('es');
        app('cache')->setDefaultDriver('array');
    }
}
