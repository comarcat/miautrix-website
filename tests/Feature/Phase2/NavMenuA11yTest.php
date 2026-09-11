<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

/**
 * E3-T8 (Phase 2, p2-step-24) — the flat nav is replaced by `<x-nav-menu>`: an ARIA menubar
 * exposing every Phase-1 destination, with Esc/focus wiring on its submenus, chromed entirely
 * by `--menu-*` custom properties defined in both theme blocks of app.css.
 */
class NavMenuA11yTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menubar_exposes_every_phase1_destination(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $crawler = new Crawler($html);

        $this->assertSame(1, $crawler->filter('[role="menubar"]')->count());
        $this->assertGreaterThanOrEqual(2, $crawler->filter('[role="menu"]')->count(), 'expected Work + Writing submenus');
        $this->assertGreaterThanOrEqual(1, $crawler->filter('[role="menuitem"]')->count());

        foreach ([
            route('home'), route('experience'), route('skills'), route('projects.index'),
            route('resume'), route('blog.index'), route('about'), route('connect'), route('contact'),
        ] as $href) {
            $this->assertStringContainsString('href="' . $href . '"', $html, "menu is missing {$href}");
        }
    }

    public function test_submenu_triggers_carry_haspopup_and_escape_focus_wiring(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-haspopup="true"', $html);
        $this->assertStringContainsString(':aria-expanded=', $html);
        $this->assertStringContainsString('keydown.escape', $html);
        // Esc closes and returns focus to the trigger; Tab is trapped back to the trigger.
        $this->assertStringContainsString('closeAll', $html);
        $this->assertStringContainsString('closeAndFocusTrigger', $html);
        $this->assertStringContainsString('x-ref="trigger-work"', $html);
    }

    public function test_the_menu_chrome_uses_only_defined_menu_tokens(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $blade = file_get_contents(resource_path('views/components/nav-menu.blade.php'));

        $keys = ['--menu-bg', '--menu-border', '--menu-shadow', '--menu-highlight'];

        foreach ($keys as $key) {
            // Defined in the Console :root block, the dark @media block, and the matrix block.
            $this->assertGreaterThanOrEqual(3, substr_count($css, $key . ':'), "{$key} not defined in all three theme blocks");
            $this->assertContains($key, Theme::DOCUMENTED_TOKEN_KEYS, "{$key} missing from the documented token set");
        }

        // The component references the menu tokens and never a raw colour for its chrome.
        $this->assertStringContainsString('--menu-bg', $blade);
        $this->assertDoesNotMatchRegularExpression('/bg-\[#|border-\[#/', $blade);
    }

    public function test_the_menubar_renders_in_both_themes(): void
    {
        Theme::factory()->default()->create(['key' => 'technical']);
        Theme::factory()->create(['key' => 'matrix', 'enabled' => true]);

        foreach (['technical' => null, 'matrix' => 'matrix'] as $theme => $cookie) {
            $req = $cookie ? $this->withCookie(ResolveTheme::COOKIE_NAME, $cookie) : $this;
            $req->get(route('home'))
                ->assertOk()
                ->assertSee('role="menubar"', false);
        }
    }
}
