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
 * E3-T4 (Phase 2, p2-step-20) — a non-seed active theme injects one nonce'd `:root` token
 * <style>; the seed themes inject none; ThemeController rejects a key that is not an enabled,
 * currently-active theme; the switcher lists only enabled in-window themes.
 */
class ThemeTokenInjectionTest extends TestCase
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

    private function seedTechnical(): void
    {
        Theme::factory()->default()->create(['key' => 'technical', 'name' => 'Console']);
    }

    public function test_a_non_seed_theme_injects_one_nonced_root_token_style_block(): void
    {
        $this->withSiteFlag('site.themes.dynamic');
        $this->seedTechnical();
        Theme::factory()->create([
            'key' => 'winterfest',
            'name' => 'Winterfest',
            'enabled' => true,
            'active_from' => now()->subDay(),
            'active_until' => now()->addDay(),
            'tokens' => ['--color-accent' => '#0af', '--color-bg' => '#012'],
        ]);

        $html = $this->withCookie(ResolveTheme::COOKIE_NAME, 'winterfest')
            ->get('/__test/themed-page')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<style nonce="[^"]+">:root\{/', (string) $html);
        $this->assertStringContainsString('--color-accent:#0af', (string) $html);
        $this->assertStringContainsString('--color-bg:#012', (string) $html);
    }

    public function test_a_seed_theme_injects_no_token_style_block(): void
    {
        $this->withSiteFlag('site.themes.dynamic');
        $this->seedTechnical();
        Theme::factory()->create(['key' => 'matrix', 'name' => 'Matrix', 'enabled' => true]);

        foreach (['technical' => null, 'matrix' => 'matrix'] as $key => $cookie) {
            $request = $cookie ? $this->withCookie(ResolveTheme::COOKIE_NAME, $cookie) : $this;
            $html = $request->get('/__test/themed-page')->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression('/<style nonce="[^"]+">:root\{/', (string) $html, "seed theme {$key} injected a token block");
        }
    }

    public function test_posting_an_inactive_theme_key_is_rejected(): void
    {
        $this->withSiteFlag('site.themes.dynamic');
        $this->seedTechnical();
        Theme::factory()->create([
            'key' => 'springtime',
            'enabled' => true,
            'active_from' => now()->addDays(10),
            'active_until' => now()->addDays(20),
        ]);
        Theme::factory()->disabled()->create(['key' => 'autumn']);

        $this->from('/__test/themed-page')->post(route('theme.set'), ['theme' => 'springtime'])
            ->assertSessionHasErrors('theme');
        $this->from('/__test/themed-page')->post(route('theme.set'), ['theme' => 'autumn'])
            ->assertSessionHasErrors('theme');
        $this->from('/__test/themed-page')->post(route('theme.set'), ['theme' => 'nope'])
            ->assertSessionHasErrors('theme');

        $this->from('/__test/themed-page')->post(route('theme.set'), ['theme' => 'technical'])
            ->assertSessionHasNoErrors()
            ->assertCookie(ResolveTheme::COOKIE_NAME, 'technical');
    }

    public function test_the_switcher_lists_only_enabled_in_window_themes(): void
    {
        $this->withSiteFlag('site.themes.dynamic');
        $this->seedTechnical();
        Theme::factory()->create([
            'key' => 'winterfest', 'name' => 'Winterfest', 'enabled' => true,
            'active_from' => now()->subDay(), 'active_until' => now()->addDay(),
        ]);
        Theme::factory()->disabled()->create(['key' => 'autumn', 'name' => 'Autumn']);
        Theme::factory()->create([
            'key' => 'springtime', 'name' => 'Springtime', 'enabled' => true,
            'active_from' => now()->addDays(10),
        ]);

        $html = $this->get('/__test/themed-page')->assertOk()->getContent();

        $this->assertStringContainsString('Switch to Winterfest', (string) $html);
        $this->assertStringNotContainsString('Switch to Autumn', (string) $html);
        $this->assertStringNotContainsString('Switch to Springtime', (string) $html);
    }
}
