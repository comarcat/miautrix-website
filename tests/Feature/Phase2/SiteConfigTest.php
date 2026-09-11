<?php

namespace Tests\Feature\Phase2;

use Tests\TestCase;

/**
 * E1-T1 (Phase 2, p2-step-01) — `config/site.php` carries the canonical host and the three
 * cross-cutting feature flags. This gate proves two things a later task depends on:
 *
 *  - every flag reads `false` on a plain checkout (no env override), so merging any Phase 2
 *    task leaves the live site byte-identical until its epic's final task flips the default;
 *  - `canonical_host` is wired to `CANONICAL_HOST` with `miautrix.tech` as the literal
 *    fallback.
 *
 * The override cases re-`require` the config file directly so `env()` re-evaluates against a
 * `putenv()`'d value — the framework has already booted and cached `config()` by the time a
 * test runs, so this is the only way to exercise the env wiring itself rather than a value
 * `Config::set()` would just echo back.
 */
class SiteConfigTest extends TestCase
{
    public function test_the_feature_flags_have_their_expected_defaults(): void
    {
        // site.themes.dynamic (E3-T9, p2-step-25) and site.csp.youtube_on_life (E4-T9,
        // p2-step-34) are both on now that their epics shipped; analytics stays off until
        // Epic 05's own final task.
        $this->assertTrue(config('site.themes.dynamic'));
        $this->assertFalse(config('site.analytics.record_page_views'));
        $this->assertTrue(config('site.csp.youtube_on_life'));
    }

    public function test_canonical_host_defaults_to_the_literal_apex_and_honours_an_env_override(): void
    {
        $this->assertSame('miautrix.tech', config('site.canonical_host'));

        putenv('CANONICAL_HOST=staging.miautrix.tech');
        try {
            $fresh = require config_path('site.php');
            $this->assertSame('staging.miautrix.tech', $fresh['canonical_host']);
        } finally {
            putenv('CANONICAL_HOST');
        }
    }

    public function test_each_flag_is_wired_to_its_env_var(): void
    {
        putenv('SITE_THEMES_DYNAMIC=true');
        putenv('SITE_ANALYTICS_RECORD_PAGE_VIEWS=true');
        putenv('SITE_CSP_YOUTUBE_ON_LIFE=true');

        try {
            $fresh = require config_path('site.php');

            $this->assertTrue($fresh['themes']['dynamic']);
            $this->assertTrue($fresh['analytics']['record_page_views']);
            $this->assertTrue($fresh['csp']['youtube_on_life']);
        } finally {
            putenv('SITE_THEMES_DYNAMIC');
            putenv('SITE_ANALYTICS_RECORD_PAGE_VIEWS');
            putenv('SITE_CSP_YOUTUBE_ON_LIFE');
        }
    }
}
