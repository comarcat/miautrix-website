<?php

namespace Tests\Feature\Phase2;

use Tests\TestCase;

/**
 * E1-T1 (Phase 2, p2-step-01) — `config/site.php` carries the canonical host and the three
 * cross-cutting feature flags. This gate originally proved every flag read `false` on a
 * plain checkout; each flag has since been flipped on in its own epic's final task
 * (site.themes.dynamic: E3-T9/p2-step-25; site.csp.youtube_on_life: E4-T9/p2-step-34;
 * site.analytics.record_page_views: E5-T9/p2-step-43) now that every feature it guards
 * actually ships. `canonical_host` is wired to `CANONICAL_HOST` with `miautrix.tech` as the
 * literal fallback.
 *
 * The override cases re-`require` the config file directly so `env()` re-evaluates against an
 * overridden value — the framework has already booted and cached `config()` by the time a test
 * runs, so this is the only way to exercise the env wiring itself rather than a value
 * `Config::set()` would just echo back. Every real deployment env (including CI, via
 * .env.example) defines these keys, which means Laravel's dotenv has already copied them into
 * `$_ENV`/`$_SERVER` at boot; a bare `putenv()` only updates the OS environment table, which
 * `$_ENV`/`$_SERVER` take priority over on the next `env()` read, so it is silently ignored
 * whenever the key is already defined. Setting (and restoring) all three — `putenv()`, `$_ENV`,
 * `$_SERVER` — is what actually makes the override visible in that case.
 */
class SiteConfigTest extends TestCase
{
    private function withEnvOverride(string $name, string $value, callable $callback): mixed
    {
        $hadEnv = array_key_exists($name, $_ENV);
        $hadServer = array_key_exists($name, $_SERVER);
        $previousEnv = $_ENV[$name] ?? null;
        $previousServer = $_SERVER[$name] ?? null;

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;

        try {
            return $callback();
        } finally {
            if ($hadEnv) {
                $_ENV[$name] = $previousEnv;
            } else {
                unset($_ENV[$name]);
            }
            if ($hadServer) {
                $_SERVER[$name] = $previousServer;
            } else {
                unset($_SERVER[$name]);
            }
            putenv($name);
        }
    }

    public function test_the_feature_flags_have_their_expected_defaults(): void
    {
        $this->assertTrue(config('site.themes.dynamic'));
        $this->assertTrue(config('site.analytics.record_page_views'));
        $this->assertTrue(config('site.csp.youtube_on_life'));
    }

    public function test_canonical_host_defaults_to_the_literal_apex_and_honours_an_env_override(): void
    {
        $this->assertSame('miautrix.tech', config('site.canonical_host'));

        $fresh = $this->withEnvOverride('CANONICAL_HOST', 'staging.miautrix.tech', fn () => require config_path('site.php'));
        $this->assertSame('staging.miautrix.tech', $fresh['canonical_host']);
    }

    public function test_each_flag_is_wired_to_its_env_var(): void
    {
        $fresh = $this->withEnvOverride('SITE_THEMES_DYNAMIC', 'true', fn () => $this->withEnvOverride(
            'SITE_ANALYTICS_RECORD_PAGE_VIEWS', 'true', fn () => $this->withEnvOverride(
                'SITE_CSP_YOUTUBE_ON_LIFE', 'true', fn () => require config_path('site.php')
            )
        ));

        $this->assertTrue($fresh['themes']['dynamic']);
        $this->assertTrue($fresh['analytics']['record_page_views']);
        $this->assertTrue($fresh['csp']['youtube_on_life']);
    }
}
