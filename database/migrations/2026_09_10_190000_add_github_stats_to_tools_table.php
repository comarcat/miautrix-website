<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E5-T7, §9 step 41) — cached GitHub repo stats for a tool whose repo_url points at
 * github.com (backlog item 13.1). Everything nullable: a tool with no repo_url, or one whose
 * fetch has never succeeded, simply has no gh_* values to show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table): void {
            $table->integer('gh_stars')->nullable();
            $table->integer('gh_forks')->nullable();
            $table->string('gh_language')->nullable();
            $table->string('gh_license')->nullable();
            $table->timestamp('gh_pushed_at')->nullable();
            $table->timestamp('gh_fetched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table): void {
            $table->dropColumn(['gh_stars', 'gh_forks', 'gh_language', 'gh_license', 'gh_pushed_at', 'gh_fetched_at']);
        });
    }
};
