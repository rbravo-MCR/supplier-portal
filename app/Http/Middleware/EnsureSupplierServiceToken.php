<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierServiceToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.supplier_service.token');
        $givenToken = $request->bearerToken() ?: $request->header('X-Supplier-Service-Token');

        if (! is_string($expectedToken) || $expectedToken === '' || ! is_string($givenToken) || ! hash_equals($expectedToken, $givenToken)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
