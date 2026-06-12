<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHealthSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('app.health_secret');

        if (! is_string($secret) || $secret === '') {
            return $next($request);
        }

        $given = $request->bearerToken() ?: $request->header('X-Health-Secret');

        if (! is_string($given) || ! hash_equals($secret, $given)) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
