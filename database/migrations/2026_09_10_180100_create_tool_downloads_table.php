<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E5-T5, §9 step 39) — append-only per-download analytics (backlog item 13):
 * timestamp, referrer, geo (nullable — GeoLocator is null-safe without the .mmdb), UA.
 * `created_at` only, same shape as share_clicks (E2-T1) — no updated_at, no soft delete.
 * `cascadeOnDelete`: acceptance 2 — a force-deleted Tool takes its download log with it; a
 * soft-deleted one (tools.deleted_at set, the row itself untouched) keeps every row, since
 * the FK is still satisfied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('isp')->nullable();
            $table->string('referrer')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tool_id', 'created_at']);
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_downloads');
    }
};
