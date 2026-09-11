<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E4-T5, §9 step 30) — optional delivery-metrics inputs. Every column is nullable;
 * the derived percentages/booleans are never stored (Project's own accessors compute them
 * from these, never throwing when an input is missing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->decimal('budget_planned', 12, 2)->nullable();
            $table->decimal('budget_actual', 12, 2)->nullable();
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->integer('team_size')->nullable();
            $table->string('role')->nullable();
            $table->string('outcome')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn([
                'budget_planned', 'budget_actual',
                'planned_start', 'planned_end', 'actual_start', 'actual_end',
                'team_size', 'role', 'outcome',
            ]);
        });
    }
};
