<?php

namespace Tests\Feature\Phase2;

use Tests\Feature\Phase2\Concerns\TogglesSiteFlags;
use Tests\TestCase;

/**
 * E1-T2 (Phase 2, p2-step-02) — proves the `TogglesSiteFlags` helper flips a `site.*` flag ON
 * for one test and that the change does not survive into the next one. Every later Phase 2
 * feature test that needs a flag-ON path relies on both halves of that.
 */
class FlagToggleHelperTest extends TestCase
{
    use TogglesSiteFlags;

    public function test_the_helper_turns_a_site_flag_on_for_the_current_test(): void
    {
        // Uses an off-by-default flag — themes.dynamic was flipped on in E3-T9.
        $this->assertFalse(config('site.analytics.record_page_views'));

        $this->withSiteFlag('site.analytics.record_page_views', true);

        $this->assertTrue(config('site.analytics.record_page_views'));
    }

    public function test_a_flag_toggled_in_another_test_does_not_leak_into_this_one(): void
    {
        $this->assertFalse(config('site.analytics.record_page_views'));
        $this->assertFalse(config('site.csp.youtube_on_life'));
    }
}
