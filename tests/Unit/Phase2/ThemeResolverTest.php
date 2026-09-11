<?php

namespace Tests\Unit\Phase2;

use App\Models\Theme;
use App\Support\Theming\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * E3-T2 (Phase 2, p2-step-18) — ThemeResolver::active() honours the `miautrix_theme` cookie
 * only for an enabled, in-window row and otherwise falls back to the single default.
 */
class ThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    private function request(?string $cookie): Request
    {
        $request = Request::create('/', 'GET');

        if ($cookie !== null) {
            $request->cookies->set('miautrix_theme', $cookie);
        }

        return $request;
    }

    private function seedDefault(): void
    {
        Theme::factory()->default()->create(['key' => 'technical']);
    }

    public function test_it_returns_an_enabled_theme_whose_window_contains_today(): void
    {
        $this->seedDefault();
        Theme::factory()->create([
            'key' => 'winterfest',
            'enabled' => true,
            'active_from' => now()->subDays(2),
            'active_until' => now()->addDays(2),
        ]);

        $this->assertSame('winterfest', (new ThemeResolver)->active($this->request('winterfest'))->key);
    }

    public function test_a_disabled_theme_falls_back_to_default(): void
    {
        $this->seedDefault();
        Theme::factory()->disabled()->create(['key' => 'winterfest']);

        $this->assertSame('technical', (new ThemeResolver)->active($this->request('winterfest'))->key);
    }

    public function test_a_theme_outside_its_active_window_falls_back_to_default(): void
    {
        $this->seedDefault();
        Theme::factory()->create([
            'key' => 'winterfest',
            'enabled' => true,
            'active_from' => now()->addDays(5),
            'active_until' => now()->addDays(10),
        ]);

        $this->assertSame('technical', (new ThemeResolver)->active($this->request('winterfest'))->key);
    }

    public function test_an_unknown_cookie_value_falls_back_to_default(): void
    {
        $this->seedDefault();

        $this->assertSame('technical', (new ThemeResolver)->active($this->request('does-not-exist'))->key);
    }

    public function test_no_cookie_returns_the_default(): void
    {
        $this->seedDefault();
        Theme::factory()->create(['key' => 'matrix', 'enabled' => true]);

        $this->assertSame('technical', (new ThemeResolver)->active($this->request(null))->key);
    }
}
