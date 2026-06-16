<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetTeamUrlDefaults
{
    /**
     * Set the default URL parameters for team-based routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing('portalRole:id,name,code', 'currentTeam:id,name,slug');
        }

        if ($user?->currentTeam) {
            URL::defaults([
                'current_team' => $user->currentTeam->slug,
                'team' => $user->currentTeam->slug,
            ]);
        }

        return $next($request);
    }
}
