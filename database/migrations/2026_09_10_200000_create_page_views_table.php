<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E5-T8, §9 step 42) — append-only page-view log (backlog item 14), written only
 * when site.analytics.record_page_views is on (RecordPageView middleware). `created_at`
 * only — no updated_at, no soft delete, same shape as share_clicks/tool_downloads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table): void {
            $table->id();
            $table->string('path');
            $table->string('referrer_host')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device', 20)->nullable();
            $table->string('channel', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('path');
            $table->index('channel');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
