<?php

use App\Http\Middleware\EnsurePrimaryDatabaseIsAvailable;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Shared\Support\IncidentId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            EnsurePrimaryDatabaseIsAvailable::class,
        ]);

        $middleware->web(append: [
            SetTeamUrlDefaults::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Throwable $exception, Request $request): ?Response {
            if (config('app.debug')) {
                return null;
            }

            $incidentId = IncidentId::generate();
            report($exception);

            $safeMessage = "Estamos teniendo una intermitencia temporal.\nCódigo de seguimiento: {$incidentId}";

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Estamos teniendo una intermitencia temporal.',
                    'incident_id' => $incidentId,
                ], 500);
            }

            return response($safeMessage, 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        });
    })->create();
