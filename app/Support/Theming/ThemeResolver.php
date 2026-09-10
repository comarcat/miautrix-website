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
 * Pure service: no view sharing, no response mutation (ResolveTheme middleware does that,
 * E3-T3). `today` is derived from `now()` so tests can pin it with `Carbon::setTestNow()`.
 */
class ThemeResolver
{
    public function active(Request $request): Theme
    {
        $default = Theme::query()->where('is_default', true)->firstOrFail();

        $key = $request->cookie(ResolveTheme::COOKIE_NAME);

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
