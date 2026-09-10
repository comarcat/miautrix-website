<?php

namespace Tests\Feature\Phase2\Concerns;

/**
 * E1-T2 (Phase 2, p2-step-02) — a readable wrapper for flipping a `config/site.php` value ON
 * for the duration of a single test.
 *
 * The three Phase 2 features (`site.themes.dynamic`, `site.analytics.record_page_views`,
 * `site.csp.youtube_on_life`) ship OFF and are only flipped in their epic's final task, so
 * every feature test that needs to exercise a flag-ON path does it here rather than through
 * an env change. `RefreshDatabase` already hands each test a freshly-booted application, so a
 * `config()->set()` here never leaks into the next test — this trait just makes the intent
 * obvious at the call site and guards against a typo pointing it at a non-`site.*` key.
 */
trait TogglesSiteFlags
{
    /**
     * Set a `site.*` config value for this test only.
     */
    protected function withSiteFlag(string $key, mixed $value = true): void
    {
        if (! str_starts_with($key, 'site.')) {
            throw new \InvalidArgumentException(
                "withSiteFlag() only toggles config/site.php values; got [{$key}]."
            );
        }

        config()->set($key, $value);
    }
}
