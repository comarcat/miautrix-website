<?php

namespace Tests\Feature\Public;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression test for a real production request: "I should be able to change the text
 * before the blog post from the admin console" — the home page's hero eyebrow/heading/
 * subheading now come from the Settings resource (a plain key/value store already built for
 * exactly this), falling back to the original copy when nothing's been set.
 */
class HomeHeroSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_falls_back_to_the_original_copy_when_no_setting_exists(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Portfolio · Blog · CMS');
        $response->assertSee('Building reliable systems, end to end.');
    }

    public function test_the_home_page_shows_the_configured_hero_text_instead(): void
    {
        Setting::put('home_hero_eyebrow', 'Now Hiring · Ask Me Anything');
        Setting::put('home_hero_heading', 'A completely different headline.');
        Setting::put('home_hero_subheading', 'A completely different subheading.');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Now Hiring · Ask Me Anything');
        $response->assertSee('A completely different headline.');
        $response->assertSee('A completely different subheading.');
        $response->assertDontSee('Building reliable systems, end to end.');
    }

    public function test_saving_a_setting_invalidates_the_home_pages_cache_entry_immediately(): void
    {
        $this->get(route('home'))->assertSee('Building reliable systems, end to end.');
        $this->assertTrue(Cache::has('public-page:technical:/'));

        Setting::put('home_hero_heading', 'Edited from the admin console.');

        $this->assertFalse(Cache::has('public-page:technical:/'));
        $this->get(route('home'))->assertSee('Edited from the admin console.');
    }
}
