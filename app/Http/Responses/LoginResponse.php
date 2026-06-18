<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToPortalPanel;
use App\Support\SupportedLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    use RedirectsToPortalPanel;

    public function toResponse($request): Response
    {
        $locale = SupportedLocale::normalize($request->user()?->preferred_locale);

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $this->redirectPathForPortalPanel($request)], 200)
            : redirect()->intended($this->redirectPathForPortalPanel($request));
    }
}
