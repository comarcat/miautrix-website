<?php

namespace Tests\Feature\Phase2;

use App\Models\Tool;
use App\Support\Github\RepoStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E5-T7 (Phase 2, p2-step-41) — cached GitHub repo stats: a stale/never-fetched tool fetches
 * once and persists gh_*; a second view within the hour makes no second HTTP request; a
 * failed request falls back to the last stored values and never 5xxs the page.
 */
class RepoStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tools_table_has_the_gh_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('tools', [
            'gh_stars', 'gh_forks', 'gh_language', 'gh_license', 'gh_pushed_at', 'gh_fetched_at',
        ]));
    }

    public function test_a_stale_tool_fetches_and_persists_stats(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'stargazers_count' => 42,
                'forks_count' => 7,
                'language' => 'PHP',
                'license' => ['spdx_id' => 'MIT'],
                'pushed_at' => '2026-01-01T00:00:00Z',
            ], 200),
        ]);

        $tool = Tool::factory()->create(['repo_url' => 'https://github.com/example/a-tool', 'gh_fetched_at' => null]);

        $stats = $tool->repoStats;

        Http::assertSentCount(1);
        $this->assertSame(42, $stats['stars']);
        $this->assertSame(7, $stats['forks']);
        $this->assertSame('PHP', $stats['language']);
        $this->assertSame('MIT', $stats['license']);
        $this->assertNotNull($stats['fetched_at']);
        $this->assertSame(42, $tool->fresh()->gh_stars);
    }

    public function test_viewing_again_within_the_hour_makes_no_second_request(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['stargazers_count' => 1, 'forks_count' => 0], 200),
        ]);

        $tool = Tool::factory()->create(['repo_url' => 'https://github.com/example/a-tool', 'gh_fetched_at' => null]);

        app(RepoStats::class)->for($tool);
        app(RepoStats::class)->for($tool->fresh());

        Http::assertSentCount(1);
    }

    public function test_a_failed_request_falls_back_to_stored_values_and_never_errors(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(null, 503),
        ]);

        $tool = Tool::factory()->create([
            'repo_url' => 'https://github.com/example/a-tool',
            'gh_fetched_at' => null,
            'gh_stars' => 10,
            'gh_forks' => 2,
        ]);

        $stats = $tool->repoStats;

        $this->assertSame(10, $stats['stars']);
        $this->assertSame(2, $stats['forks']);
        $this->assertNull($stats['fetched_at'], 'a failed fetch must not bump gh_fetched_at');
    }
}
