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
 *
 * Found in production review: a fresh admin landing here (via this redirect, with no error
 * of their own making) saw only the account/password/2FA/passkey settings — no explanation
 * of why, and nothing that looks like "the admin panel" at all — and reported it as content
 * management being entirely missing. The redirect itself is correct (E2-T5); what was
 * missing was telling the admin WHY they're here. `mfaRequired` flashes a one-time notice
 * the security settings page renders as a banner (see resources/views/pages/settings/security.blade.php).
 */
class EnsureMfaConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && is_null($user->two_factor_confirmed_at)) {
            return redirect()->route('security.edit')->with('mfaRequired', true);
        }

        return $next($request);
    }
}
