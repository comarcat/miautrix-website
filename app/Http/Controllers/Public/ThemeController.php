<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTheme;
use App\Models\Theme;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * E4-T2 (§9 step 20) — sets the theme cookie the switcher offers, then redirects back to
 * the referring page. A plain form POST (not a fetch call) is what makes the "reload with
 * no flash" acceptance criterion trivially true: the cookie is already set by the time the
 * browser's own navigation re-requests the page, so ResolveTheme reads the new value on
 * that very next response — no client-side re-render step to get wrong.
 *
 * Phase 2 (E3-T4, §9 step 20): with `site.themes.dynamic` OFF the accepted set is still the
 * two literals; with it ON the key must name a row in `themes` that is `enabled` and whose
 * active window contains today — the same test ThemeResolver applies when reading the cookie.
 */
class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', $this->themeRule()],
        ]);

        return back()
            ->withCookie(cookie(
                name: ResolveTheme::COOKIE_NAME,
                value: $validated['theme'],
                minutes: 60 * 24 * 365,
            ));
    }

    private function themeRule(): Closure|In
    {
        if (! config('site.themes.dynamic')) {
            return Rule::in(['technical', 'matrix']);
        }

        return function (string $attribute, mixed $value, Closure $fail): void {
            $available = Theme::query()
                ->where('key', $value)
                ->where('enabled', true)
                ->where(fn ($query) => $query->whereNull('active_from')->orWhereDate('active_from', '<=', now()))
                ->where(fn ($query) => $query->whereNull('active_until')->orWhereDate('active_until', '>=', now()))
                ->exists();

            if (! $available) {
                $fail('The selected theme is not available.');
            }
        };
    }
}
