<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks any `/admin/*` request when the authenticated user hasn't confirmed TOTP MFA yet —
 * MFA is mandatory for admin access (blueprint §4/§9 step 10). Redirects to the security
 * settings page, where Fortify's own two-factor enrolment flow (QR + recovery codes, shown
 * once) lives.
 */
class EnsureMfaConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && is_null($user->two_factor_confirmed_at)) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
