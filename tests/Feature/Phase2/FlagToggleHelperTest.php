<?php

namespace Tests\Feature\Phase2;

use Tests\Feature\Phase2\Concerns\TogglesSiteFlags;
use Tests\TestCase;

/**
 * E1-T2 (Phase 2, p2-step-02) — proves the `TogglesSiteFlags` helper flips a `site.*` flag
 * for one test and that the change does not survive into the next one. Every later Phase 2
 * feature test that needs a flag-OFF (or ON) path relies on both halves of that.
 *
 * All three Phase 2 flags now default to `true` (the last, site.analytics.record_page_views,
 * flipped in E5-T9) — each epic's final task turned its own flag on once the feature it
 * guards actually shipped — so this toggles one OFF rather than ON to exercise the helper.
 */
class FlagToggleHelperTest extends TestCase
{
    use TogglesSiteFlags;

    public function test_the_helper_turns_a_site_flag_off_for_the_current_test(): void
    {
        $this->assertTrue(config('site.analytics.record_page_views'));

        $this->withSiteFlag('site.analytics.record_page_views', false);

        $this->assertFalse(config('site.analytics.record_page_views'));
    }

    public function test_a_flag_toggled_in_another_test_does_not_leak_into_this_one(): void
    {
        // RefreshDatabase boots a fresh application per test — the previous test's
        // withSiteFlag(false) call never reaches this one; every flag is back to its own
        // real default.
        $this->assertTrue(config('site.analytics.record_page_views'));
        $this->assertTrue(config('site.themes.dynamic'));
        $this->assertTrue(config('site.csp.youtube_on_life'));
    }
}
