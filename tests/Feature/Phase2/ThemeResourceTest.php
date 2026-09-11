<?php

namespace Tests\Feature\Phase2;

use App\Filament\Resources\Themes\Pages\CreateTheme;
use App\Filament\Resources\Themes\ThemeResource;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * E3-T5 (Phase 2, p2-step-21) — the super_admin-only Themes Filament resource persists the
 * full row (KeyValue tokens, date pickers, toggles) and ThemeObserver busts every
 * public-page cache entry on any theme save or delete.
 */
class ThemeResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->withTwoFactor()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_the_index_is_reachable_for_the_super_admin_and_denied_otherwise(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(ThemeResource::getUrl('index'))
            ->assertOk();

        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(ThemeResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_creating_a_theme_persists_tokens_dates_and_the_life_blog_toggle(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateTheme::class)
            ->fillForm([
                'key' => 'winterfest',
                'name' => 'Winterfest',
                'tokens' => ['--color-accent' => '#0af', '--color-bg' => '#012'],
                'active_from' => '2026-12-01',
                'active_until' => '2026-12-31',
                'enabled' => true,
                'shows_life_blog' => true,
                'sort_order' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $theme = Theme::where('key', 'winterfest')->firstOrFail();

        $this->assertSame(['--color-accent' => '#0af', '--color-bg' => '#012'], $theme->tokens);
        $this->assertSame('2026-12-01', $theme->active_from->toDateString());
        $this->assertSame('2026-12-31', $theme->active_until->toDateString());
        $this->assertTrue($theme->shows_life_blog);
        $this->assertSame(3, $theme->sort_order);
    }

    public function test_saving_or_deleting_a_theme_busts_the_whole_public_page_cache(): void
    {
        $keys = [
            'public-page:127.0.0.1:technical:/',
            'public-page:127.0.0.1:matrix:projects',
            'public-page:miautrix.tech:technical:blog',
            'public-page:www.miautrix.tech:matrix:skills',
        ];

        foreach ($keys as $key) {
            Cache::put($key, 'stale', 600);
        }

        $theme = Theme::factory()->create(['key' => 'aurora']);

        foreach ($keys as $key) {
            $this->assertFalse(Cache::has($key), "save left {$key} stale");
        }

        foreach ($keys as $key) {
            Cache::put($key, 'stale', 600);
        }

        $theme->delete();

        foreach ($keys as $key) {
            $this->assertFalse(Cache::has($key), "delete left {$key} stale");
        }
    }
}
