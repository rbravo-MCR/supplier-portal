<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToPortalPanel;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsToPortalPanel;

    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $this->redirectPathForPortalPanel($request)], 201)
            : redirect()->intended($this->redirectPathForPortalPanel($request));
    }
}
