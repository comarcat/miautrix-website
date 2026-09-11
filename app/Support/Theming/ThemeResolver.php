<?php

namespace App\Support\Theming;

use App\Http\Middleware\ResolveTheme;
use App\Models\Theme;
use Illuminate\Http\Request;

/**
 * Phase 2 (E3-T2, §9 step 18) — resolves the request's active Theme row from the
 * `miautrix_theme` cookie.
 *
 * A cookie value is honoured only when it names a row that is `enabled` AND whose
 * `[active_from, active_until]` window (null bounds = unbounded) contains today; anything
 * else — unknown key, disabled row, out-of-window special-event theme, or no cookie at all —
 * falls back to the single `is_default` row.
 *
 * When the `themes` table has no `is_default` row (a fresh DB where ThemeSeeder has not run
 * yet), this degrades to the exact pre-Phase-2 literal path — `matrix` only when the cookie
 * says so, else `technical` — via an unsaved synthetic Theme. So turning `site.themes.dynamic`
 * on (E3-T9) is safe even before the seed runs: nothing 500s, behaviour is unchanged until
 * real theme rows exist.
 *
 * Pure service: no view sharing, no response mutation (ResolveTheme middleware does that,
 * E3-T3). `today` is derived from `now()` so tests can pin it with `Carbon::setTestNow()`.
 */
class ThemeResolver
{
    public function active(Request $request): Theme
    {
        $key = $request->cookie(ResolveTheme::COOKIE_NAME);
        $default = Theme::query()->where('is_default', true)->first();

        if ($default === null) {
            $literal = $key === 'matrix' ? 'matrix' : 'technical';

            return new Theme([
                'key' => $literal,
                'name' => $literal === 'matrix' ? 'Matrix' : 'Console',
                'tokens' => [],
                'is_default' => true,
                'enabled' => true,
                'shows_life_blog' => $literal === 'matrix',
            ]);
        }

        if (! is_string($key) || $key === '' || $key === $default->key) {
            return $default;
        }

        $today = now()->startOfDay();

        $candidate = Theme::query()
            ->where('key', $key)
            ->where('enabled', true)
            ->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', $today))
            ->first();

        return $candidate ?? $default;
    }
}
