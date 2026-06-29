<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierServiceSource
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('supplier.service.allowed_ips', []);

        if (! is_array($allowedIps) || $allowedIps === []) {
            abort_if(config('app.env') === 'production', Response::HTTP_FORBIDDEN);

            return $next($request);
        }

        abort_unless(IpUtils::checkIp($request->ip() ?? '', $allowedIps), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
