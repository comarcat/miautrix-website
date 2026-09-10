<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Phase2\Concerns\TogglesSiteFlags;
use Tests\TestCase;

/**
 * E3-T3 (Phase 2, p2-step-19) — ResolveTheme keeps the pre-Phase-2 literal cookie path when
 * `site.themes.dynamic` is OFF and delegates to ThemeResolver when it is ON.
 */
class ResolveThemeDelegationTest extends TestCase
{
    use RefreshDatabase;
    use TogglesSiteFlags;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/themed-page', fn () => Blade::render(
            '<x-layouts::app title="Test" description="Test description">Body</x-layouts::app>'
        ));
    }

    public function test_flag_off_matrix_cookie_still_resolves_to_matrix(): void
    {
        $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('data-theme="matrix"', false);
    }

    public function test_flag_off_unknown_cookie_resolves_to_technical(): void
    {
        $this->withCookie(ResolveTheme::COOKIE_NAME, 'winterfest')
            ->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('data-theme="technical"', false);
    }

    public function test_flag_on_resolves_a_future_dated_event_theme_to_default(): void
    {
        $this->withSiteFlag('site.themes.dynamic');
        Theme::factory()->default()->create(['key' => 'technical']);
        Theme::factory()->create([
            'key' => 'winterfest',
            'enabled' => true,
            'active_from' => now()->addDays(10),
            'active_until' => now()->addDays(20),
        ]);

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'winterfest')
            ->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('data-theme="technical"', false);
    }

    public function test_flag_on_resolves_an_in_window_event_theme_from_the_cookie(): void
    {
        $this->withSiteFlag('site.themes.dynamic');
        Theme::factory()->default()->create(['key' => 'technical']);
        Theme::factory()->create([
            'key' => 'winterfest',
            'enabled' => true,
            'active_from' => now()->subDay(),
            'active_until' => now()->addDay(),
        ]);

        $this->withCookie(ResolveTheme::COOKIE_NAME, 'winterfest')
            ->get('/__test/themed-page')
            ->assertOk()
            ->assertSee('data-theme="winterfest"', false);
    }
}
