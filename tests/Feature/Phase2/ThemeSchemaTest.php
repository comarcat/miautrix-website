<?php

namespace Tests\Feature\Phase2;

use App\Models\Theme;
use Database\Seeders\ThemeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E3-T1 (Phase 2, p2-step-17) — the `themes` table, the `Theme` model's single-default
 * invariant, and the standalone admin-user-free `ThemeSeeder`.
 */
class ThemeSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_themes_table_has_the_expected_shape(): void
    {
        $this->assertTrue(Schema::hasTable('themes'));
        $this->assertTrue(Schema::hasColumns('themes', [
            'key', 'name', 'tokens', 'is_default', 'enabled',
            'active_from', 'active_until', 'shows_life_blog', 'sort_order',
            'created_at', 'updated_at',
        ]));

        Theme::factory()->create(['key' => 'aurora']);

        $this->expectException(QueryException::class);
        Theme::factory()->create(['key' => 'aurora']);
    }

    public function test_the_theme_seeder_creates_the_two_baseline_rows_without_admin_env(): void
    {
        // No ADMIN_SEED_* set in the test env — the seeder must not need them.
        (new ThemeSeeder)->run();

        $technical = Theme::where('key', 'technical')->firstOrFail();
        $matrix = Theme::where('key', 'matrix')->firstOrFail();

        $this->assertSame(1, Theme::where('is_default', true)->count());
        $this->assertTrue($technical->is_default);
        $this->assertTrue($technical->enabled);
        $this->assertFalse($technical->shows_life_blog);
        $this->assertSame([], $technical->tokens);

        $this->assertFalse($matrix->is_default);
        $this->assertTrue($matrix->enabled);
        $this->assertTrue($matrix->shows_life_blog);
        $this->assertSame([], $matrix->tokens);

        // Idempotent — updateOrCreate keyed on `key`.
        (new ThemeSeeder)->run();
        $this->assertSame(2, Theme::count());
    }

    public function test_saving_a_second_default_theme_demotes_the_previous_one(): void
    {
        $first = Theme::factory()->default()->create(['key' => 'first']);
        $second = Theme::factory()->default()->create(['key' => 'second']);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, Theme::where('is_default', true)->count());

        // Flipping an existing row to default also demotes the current holder.
        $first->refresh()->update(['is_default' => true]);

        $this->assertTrue($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);
        $this->assertSame(1, Theme::where('is_default', true)->count());
    }

    public function test_tokens_round_trip_as_an_array_and_optional_columns_default_sensibly(): void
    {
        $theme = Theme::factory()->create([
            'key' => 'winterfest',
            'tokens' => ['--color-accent' => '#0af', '--color-bg' => '#012'],
        ]);

        $fresh = $theme->fresh();
        $this->assertSame(['--color-accent' => '#0af', '--color-bg' => '#012'], $fresh->tokens);
        $this->assertNull($fresh->active_from);
        $this->assertNull($fresh->active_until);
        $this->assertFalse($fresh->shows_life_blog);
        $this->assertTrue($fresh->enabled);
    }
}
