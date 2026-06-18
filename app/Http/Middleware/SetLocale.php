<?php

namespace App\Http\Middleware;

use App\Support\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = SupportedLocale::normalize(auth()->check()
            ? auth()->user()->preferred_locale
            : $request->session()->get('locale', config('app.locale')));

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
