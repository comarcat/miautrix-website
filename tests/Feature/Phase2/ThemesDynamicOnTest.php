<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use App\Models\Theme;
use App\Support\Theming\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E3-T9 (Phase 2, p2-step-25) — `site.themes.dynamic` now defaults ON. The seeded
 * technical/matrix rows still render exactly as before, a future-dated event theme is neither
 * offered nor resolved, an active one is, and a theme save busts the whole public-page cache.
 */
class ThemesDynamicOnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/themed-page', fn () => Blade::render(
            '<x-layouts::app title="Test" description="Test description">Body</x-layouts::app>'
        ));
    }

    private function seedBaseline(): void
    {
        Theme::factory()->default()->create(['key' => 'technical', 'name' => 'Console']);
        Theme::factory()->create(['key' => 'matrix', 'name' => 'Matrix', 'enabled' => true, 'shows_life_blog' => true]);
    }

    public function test_the_flag_defaults_on_and_the_seed_themes_still_render_unchanged(): void
    {
        $this->assertTrue(config('site.themes.dynamic'));

        $this->seedBaseline();

        $this->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('data-theme="technical"', false)
            ->assertDontSee(':root{', false);

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('data-theme="matrix"', false)
            ->assertDontSee(':root{', false);
    }

    public function test_a_future_dated_event_theme_is_neither_offered_nor_resolved(): void
    {
        $this->seedBaseline();
        Theme::factory()->create([
            'key' => 'springfest',
            'name' => 'Springfest',
            'enabled' => true,
            'active_from' => now()->addDays(14),
            'active_until' => now()->addDays(21),
        ]);

        $request = Request::create('/', 'GET');
        $request->cookies->set('miautrix_theme', 'springfest');
        $this->assertSame('technical', (new ThemeResolver)->active($request)->key);

        $this->get('/__test/themed-page')
            ->assertOk()
            ->assertDontSee('Switch to Springfest');
    }

    public function test_an_active_now_event_theme_is_offered_and_resolvable(): void
    {
        $this->seedBaseline();
        Theme::factory()->create([
            'key' => 'summerfest',
            'name' => 'Summerfest',
            'enabled' => true,
            'active_from' => now()->subDay(),
            'active_until' => now()->addDay(),
            'tokens' => ['--accent' => '#f80'],
        ]);

        $request = Request::create('/', 'GET');
        $request->cookies->set('miautrix_theme', 'summerfest');
        $this->assertSame('summerfest', (new ThemeResolver)->active($request)->key);

        $this->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('Switch to Summerfest');

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'summerfest')
            ->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('data-theme="summerfest"', false)
            ->assertSee('--accent:#f80', false);
    }

    public function test_saving_a_theme_busts_every_public_page_cache_entry_for_both_variants(): void
    {
        $this->seedBaseline();

        $keys = [
            'public-page:127.0.0.1:technical:/',
            'public-page:127.0.0.1:matrix:projects',
            'public-page:miautrix.tech:technical:blog',
            'public-page:www.miautrix.tech:matrix:connect',
        ];
        foreach ($keys as $key) {
            Cache::put($key, 'stale', 600);
        }

        Theme::where('key', 'matrix')->firstOrFail()->update(['sort_order' => 5]);

        foreach ($keys as $key) {
            $this->assertFalse(Cache::has($key), "{$key} not forgotten");
        }
    }
}
