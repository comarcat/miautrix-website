<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * E4-T2 (§9 step 20) — sets the theme cookie the switcher offers, then redirects back to
 * the referring page. A plain form POST (not a fetch call) is what makes the "reload with
 * no flash" acceptance criterion trivially true: the cookie is already set by the time the
 * browser's own navigation re-requests the page, so ResolveTheme reads the new value on
 * that very next response — no client-side re-render step to get wrong.
 */
class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['technical', 'matrix'])],
        ]);

        return back()
            ->withCookie(cookie(
                name: ResolveTheme::COOKIE_NAME,
                value: $validated['theme'],
                minutes: 60 * 24 * 365,
            ));
    }
}
