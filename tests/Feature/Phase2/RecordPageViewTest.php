<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Phase2\Concerns\TogglesSiteFlags;
use Tests\TestCase;

/**
 * E5-T8 (Phase 2, p2-step-42) — RecordPageView is entirely flag-gated and, even when the
 * flag is on, never writes a row for /admin*, a non-GET request, an asset path, or a known
 * bot UA.
 */
class RecordPageViewTest extends TestCase
{
    use RefreshDatabase;
    use TogglesSiteFlags;

    public function test_the_page_views_table_has_the_expected_shape(): void
    {
        $this->assertTrue(Schema::hasTable('page_views'));
        $this->assertTrue(Schema::hasColumns('page_views', [
            'path', 'referrer_host', 'country', 'device', 'channel', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('page_views', 'updated_at'));
    }

    public function test_flag_on_and_a_get_request_inserts_exactly_one_row(): void
    {
        $this->withSiteFlag('site.analytics.record_page_views');

        $this->get('/')->assertOk();

        $this->assertSame(1, PageView::count());
        $this->assertSame('professional', PageView::first()->channel);
    }

    public function test_flag_on_but_admin_non_get_asset_or_bot_insert_zero_rows(): void
    {
        $this->withSiteFlag('site.analytics.record_page_views');

        $this->get('/admin/login');
        $this->post('/theme', ['theme' => 'technical']);
        $this->get('/build/assets/app.css');
        $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')->get('/');

        $this->assertSame(0, PageView::count());
    }

    public function test_flag_off_inserts_zero_rows(): void
    {
        // Flag defaults ON as of E5-T9 — toggled off here to prove the gate itself.
        $this->withSiteFlag('site.analytics.record_page_views', false);

        $this->get('/')->assertOk();
        $this->get('/about');

        $this->assertSame(0, PageView::count());
    }

    public function test_the_life_path_is_recorded_under_the_life_channel(): void
    {
        $this->withSiteFlag('site.analytics.record_page_views');

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')->get('/life');

        $this->assertSame(1, PageView::count());
        $this->assertSame('life', PageView::first()->channel);
    }
}
