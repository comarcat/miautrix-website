<?php

namespace Tests\Unit\Phase2;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E4-T5 (Phase 2, p2-step-30) — Project's delivery-metrics accessors are computed on read,
 * never stored, and null-safe: full, partial and empty inputs, full / partial / none.
 */
class ProjectMetricsAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_four_accessors_are_null_with_no_metrics_set(): void
    {
        $project = Project::factory()->create();

        $this->assertNull($project->schedulePerformancePct);
        $this->assertNull($project->budgetPerformancePct);
        $this->assertNull($project->isOnTime);
        $this->assertNull($project->isOnBudget);
    }

    public function test_schedule_performance_pct_is_null_with_only_some_dates_set(): void
    {
        $project = Project::factory()->create([
            'planned_start' => '2026-01-01',
            'planned_end' => '2026-02-01',
            'actual_start' => null,
            'actual_end' => null,
        ]);

        $this->assertNull($project->schedulePerformancePct);
        $this->assertNull($project->isOnTime);
    }

    public function test_budget_performance_pct_is_null_with_only_one_budget_set(): void
    {
        $project = Project::factory()->create([
            'budget_planned' => 10000,
            'budget_actual' => null,
        ]);

        $this->assertNull($project->budgetPerformancePct);
        $this->assertNull($project->isOnBudget);
    }

    public function test_schedule_and_on_time_compute_from_planned_vs_actual_when_all_dates_are_set(): void
    {
        $onTime = Project::factory()->create([
            'planned_start' => '2026-01-01',
            'planned_end' => '2026-02-10', // 40 planned days
            'actual_start' => '2026-01-01',
            'actual_end' => '2026-02-05', // 35 actual days — finished early
        ]);

        $this->assertSame(round(40 / 35 * 100, 1), $onTime->schedulePerformancePct);
        $this->assertTrue($onTime->isOnTime);

        $late = Project::factory()->create([
            'planned_start' => '2026-01-01',
            'planned_end' => '2026-01-21', // 20 planned days
            'actual_start' => '2026-01-01',
            'actual_end' => '2026-02-10', // 40 actual days — finished late
        ]);

        $this->assertSame(50.0, $late->schedulePerformancePct);
        $this->assertFalse($late->isOnTime);
    }

    public function test_budget_and_on_budget_compute_from_planned_vs_actual_when_both_budgets_are_set(): void
    {
        $underBudget = Project::factory()->create([
            'budget_planned' => 10000,
            'budget_actual' => 8000,
        ]);

        $this->assertSame(125.0, $underBudget->budgetPerformancePct);
        $this->assertTrue($underBudget->isOnBudget);

        $overBudget = Project::factory()->create([
            'budget_planned' => 10000,
            'budget_actual' => 12000,
        ]);

        $this->assertSame(round(10000 / 12000 * 100, 1), $overBudget->budgetPerformancePct);
        $this->assertFalse($overBudget->isOnBudget);
    }
}
