<?php

namespace Tests\Feature\Phase2;

use App\Filament\Pages\Analytics;
use App\Models\PageView;
use App\Models\ShareClick;
use App\Models\ToolDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E5-T9 (Phase 2, p2-step-43) — the self-hosted Analytics dashboard: totals match seeded
 * page_views/tool_downloads/share_clicks over the selected range, the widgets show top
 * paths/referrers/country/channel breakdowns from first-party tables, and neither it nor a
 * public page ever loads a non-'self' <script>.
 */
class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_the_dashboard_renders_totals_matching_seeded_rows_over_the_range(): void
    {
        PageView::factory()->count(5)->create(['path' => 'about', 'created_at' => now()]);
        ToolDownload::factory()->count(3)->create(['created_at' => now()]);
        ShareClick::factory()->count(2)->create(['created_at' => now()]);

        // Outside the 7-day range — must not be counted.
        PageView::factory()->create(['created_at' => now()->subDays(30)]);

        $response = $this->actingAs($this->superAdmin())
            ->get(Analytics::getUrl(['days' => 7]));

        $response->assertOk();
        $response->assertSee((string) PageView::where('created_at', '>=', now()->subDays(7))->count());
        $response->assertSee((string) ToolDownload::where('created_at', '>=', now()->subDays(7))->count());
        $response->assertSee((string) ShareClick::where('created_at', '>=', now()->subDays(7))->count());
    }

    public function test_the_widgets_show_top_paths_referrers_country_and_channel_breakdowns(): void
    {
        PageView::factory()->create(['path' => 'projects', 'referrer_host' => 'google.com', 'country' => 'US', 'channel' => 'professional']);
        PageView::factory()->create(['path' => 'life', 'referrer_host' => 'google.com', 'country' => 'US', 'channel' => 'life']);

        $response = $this->actingAs($this->superAdmin())
            ->get(Analytics::getUrl(['days' => 0]));

        $response->assertOk();
        $response->assertSee('/projects', false);
        $response->assertSee('google.com');
        $response->assertSee('US');
        $response->assertSee('Professional');
        $response->assertSee('Life');
    }

    public function test_neither_the_dashboard_nor_a_public_page_loads_a_non_self_script(): void
    {
        $dashboard = $this->actingAs($this->superAdmin())->get(Analytics::getUrl());
        $dashboard->assertOk();
        $this->assertDoesNotMatchRegularExpression(
            '/<script[^>]+src=["\'](?!\/|https:\/\/[^"\']*\.test\/)(https?:)?\/\/(?!localhost|127\.0\.0\.1)/i',
            (string) $dashboard->getContent(),
        );

        $home = $this->get('/');
        $home->assertOk();
        $this->assertDoesNotMatchRegularExpression('/<script[^>]+src=["\']https?:\/\/(?!localhost|127\.0\.0\.1)/i', (string) $home->getContent());
    }

    public function test_record_page_views_defaults_to_true(): void
    {
        $this->assertTrue(config('site.analytics.record_page_views'));
    }
}
