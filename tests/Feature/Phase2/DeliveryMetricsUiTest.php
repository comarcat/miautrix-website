<?php

namespace Tests\Feature\Phase2;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E4-T6 (Phase 2, p2-step-31) — the delivery-metrics stat strip renders only when at least
 * one metric is set (no placeholder otherwise), its percentages match the model accessors,
 * and the card's "On time · On budget" badge renders only when both are true.
 */
class DeliveryMetricsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_stat_strip_renders_when_at_least_one_metric_is_set(): void
    {
        $project = Project::factory()->create(['slug' => 'with-team-size', 'published' => true, 'team_size' => 4]);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('Team size')
            ->assertSee('4');
    }

    public function test_the_stat_strip_renders_nothing_when_no_metric_is_set(): void
    {
        $project = Project::factory()->create(['slug' => 'no-metrics', 'published' => true]);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertDontSee('Team size')
            ->assertDontSee('Budget performance')
            ->assertDontSee('Schedule performance');
    }

    public function test_the_stat_strip_percentage_matches_the_model_accessor(): void
    {
        $project = Project::factory()->create([
            'slug' => 'budget-metrics',
            'published' => true,
            'budget_planned' => 10000,
            'budget_actual' => 8000,
        ]);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee($project->budgetPerformancePct . '%');
    }

    public function test_the_card_badge_renders_only_when_both_on_time_and_on_budget(): void
    {
        Project::factory()->create([
            'title' => 'Both On Time And Budget', 'slug' => 'both', 'published' => true,
            'planned_end' => '2026-02-01', 'actual_end' => '2026-01-30',
            'budget_planned' => 1000, 'budget_actual' => 900,
        ]);
        Project::factory()->create([
            'title' => 'Only On Time', 'slug' => 'only-on-time', 'published' => true,
            'planned_end' => '2026-02-01', 'actual_end' => '2026-01-30',
            'budget_planned' => 1000, 'budget_actual' => 1200,
        ]);

        $response = $this->get(route('projects.index'))->assertOk();

        $response->assertSeeInOrder(['Both On Time And Budget', 'On time &middot; On budget', 'Only On Time'], false);
        $this->assertSame(1, substr_count($response->getContent(), 'On time &middot; On budget'));
    }
}
