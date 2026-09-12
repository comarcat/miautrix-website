<?php

namespace Tests\Feature\Phase2;

use App\Http\Middleware\ResolveTheme;
use App\Livewire\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E5-T3 (Phase 2, p2-step-37) — the Livewire terminal widget: help lists every command,
 * `cd <page>` redirects, `matrix` sets the theme cookie, `dir`/`ls` hides /life under a
 * non-permitted theme, and the widget never mounts inside the admin panel.
 */
class TerminalWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_lists_every_command(): void
    {
        Livewire::test(Terminal::class)
            ->set('input', 'help')
            ->call('run')
            ->assertSee('help')
            ->assertSee('dir')
            ->assertSee('cd')
            ->assertSee('whoami')
            ->assertSee('clear')
            ->assertSee('matrix');
    }

    public function test_cd_projects_redirects_to_the_projects_page(): void
    {
        Livewire::test(Terminal::class)
            ->set('input', 'cd projects')
            ->call('run')
            ->assertRedirect(route('projects.index'));
    }

    public function test_matrix_sets_the_theme_cookie(): void
    {
        Livewire::test(Terminal::class)
            ->set('input', 'matrix')
            ->call('run')
            ->assertRedirect(route('home'));

        $cookie = collect(Cookie::getQueuedCookies())
            ->first(fn ($c) => $c->getName() === ResolveTheme::COOKIE_NAME);

        $this->assertNotNull($cookie, 'miautrix_theme cookie was not queued');
        $this->assertSame('matrix', $cookie->getValue());
    }

    public function test_dir_hides_life_under_a_non_permitted_theme(): void
    {
        Livewire::test(Terminal::class)
            ->set('input', 'dir')
            ->call('run')
            ->assertSee('projects')
            ->assertDontSee('life');
    }

    /**
     * Regression test for a real bug found live (reported as "no under the dir command"):
     * CR-P2-13 (Tools) and CR-P2-15 (Endorsements) both shipped working public routes that
     * were never added to Terminal::PAGES, so a visitor typing `dir` had no way to discover
     * either page existed.
     */
    public function test_dir_lists_tools_and_endorsements(): void
    {
        Livewire::test(Terminal::class)
            ->set('input', 'dir')
            ->call('run')
            ->assertSee('tools')
            ->assertSee('endorsements');
    }

    public function test_cd_tools_and_cd_endorsements_redirect_to_the_right_pages(): void
    {
        Livewire::test(Terminal::class)
            ->set('input', 'cd tools')
            ->call('run')
            ->assertRedirect(route('tools.index'));

        Livewire::test(Terminal::class)
            ->set('input', 'cd endorsements')
            ->call('run')
            ->assertRedirect(route('endorsements.index'));
    }

    public function test_the_admin_panel_never_mounts_the_terminal_widget(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('terminal');
    }
}
