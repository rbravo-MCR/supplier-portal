<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToPortalPanel;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use RedirectsToPortalPanel;

    public function toResponse($request): Response
    {
        $redirect = $this->redirectPathForPortalPanel($request);

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => redirect()->intended($redirect)->getTargetUrl()], 200)
            : redirect()->intended($redirect);
    }
}
