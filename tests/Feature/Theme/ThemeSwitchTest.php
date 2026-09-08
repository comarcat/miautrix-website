<?php

namespace Tests\Feature\Theme;

use App\Http\Middleware\ResolveTheme;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E4-T2 — Matrix theme + cookie-persisted SSR-correct theme switcher (§9 step 20).
 *
 * No real public page renders `x-layouts::app` yet (that lands in E4-T4/T5/T6), so these
 * register a throwaway route through the real `web` middleware group — the same group
 * ResolveTheme is appended to in bootstrap/app.php — to exercise the full request/response
 * cycle the acceptance criteria actually describe ("on the very first response"), rather
 * than rendering the layout as a disconnected Blade string the way E4-T1's test does.
 */
class ThemeSwitchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rendered as a real component tag (not a plain view() call) so `@props` resolves
        // against an actual $attributes bag, exactly as it would for any real page that
        // uses this layout once E4-T4/T5/T6 land.
        Route::middleware('web')->get('/__test/themed-page', function () {
            return Blade::render(
                '<x-layouts::app title="Test" description="Test description">Body content</x-layouts::app>'
            );
        });
    }

    public function test_theme_defaults_to_technical_when_the_cookie_is_absent(): void
    {
        $response = $this->get('/__test/themed-page');

        $response->assertOk();
        $response->assertSee('data-theme="technical"', false);
    }

    public function test_matrix_cookie_renders_data_theme_matrix_on_the_very_first_response(): void
    {
        $response = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get('/__test/themed-page');

        $response->assertOk();
        $response->assertSee('data-theme="matrix"', false);
    }

    public function test_posting_the_theme_sets_the_cookie_and_redirects_back(): void
    {
        $response = $this->from('/__test/themed-page')
            ->post(route('theme.set'), ['theme' => 'matrix']);

        $response->assertRedirect('/__test/themed-page');
        $response->assertCookie(ResolveTheme::COOKIE_NAME, 'matrix');

        // The next request — the browser's own post-redirect navigation — sees Matrix
        // immediately, with no separate client-side toggle step.
        $next = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get('/__test/themed-page');
        $next->assertSee('data-theme="matrix"', false);
    }

    public function test_switcher_shows_the_overrides_system_theme_label_only_in_matrix_mode(): void
    {
        $technical = $this->get('/__test/themed-page');
        $technical->assertDontSee('overrides system theme');

        $matrix = $this->withCookie(ResolveTheme::COOKIE_NAME, 'matrix')
            ->get('/__test/themed-page');
        $matrix->assertSee('overrides system theme');
    }
}
