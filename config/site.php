<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Canonical host
    |--------------------------------------------------------------------------
    |
    | The one hostname all traffic canonicalises to (Phase 2, item 10). Drives the
    | shared session/theme cookie domain and the <link rel="canonical"> emitted on
    | both `www.` and the apex. Kept as a literal default so a plain checkout — and
    | every test — behaves exactly as it does today.
    |
    */

    'canonical_host' => env('CANONICAL_HOST', 'miautrix.tech'),

    /*
    |--------------------------------------------------------------------------
    | Phase 2 feature flags — all default OFF
    |--------------------------------------------------------------------------
    |
    | Each of the three cross-cutting Phase 2 features hides behind one of these
    | booleans. OFF means the feature is inert and the site is byte-identical to
    | before Phase 2; the flag is flipped to `true` only in its epic's final task
    | (p2-step-25 / p2-step-43 / p2-step-34). Flipping a flag back to `false` in the
    | environment disables the feature with no redeploy — the Phase 2 rollback lever.
    |
    | Nothing reads these yet; later tasks consume them.
    |
    */

    'themes' => [
        // ThemeResolver / date-windowed event themes. ON as of E3-T9 (p2-step-25) — the
        // whole Epic 03 chain (schema, resolver, delegation, token injection, admin, host
        // canonicalisation, menubar) is in place. Set SITE_THEMES_DYNAMIC=false to fall
        // back to the pre-Phase-2 literal path (cookie === 'matrix' ? 'matrix' : 'technical').
        'dynamic' => env('SITE_THEMES_DYNAMIC', true),
    ],

    'analytics' => [
        // RecordPageView middleware. OFF: it early-returns before any DB write and
        // `page_views` gets no rows.
        'record_page_views' => env('SITE_ANALYTICS_RECORD_PAGE_VIEWS', false),
    ],

    'csp' => [
        // The `/life*`-scoped YouTube frame-src / i.ytimg.com img-src addition in
        // SecurityHeaders. ON as of E4-T9 (p2-step-34) — the click-to-play facade
        // (youtube-embed.blade.php) and the Life blog it appears on (E4-T8) both exist.
        // Set SITE_CSP_YOUTUBE_ON_LIFE=false to fall back to every route's pre-Phase-2 CSP.
        'youtube_on_life' => env('SITE_CSP_YOUTUBE_ON_LIFE', true),
    ],

];
