<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E5-T5, §9 step 39) — downloadable tools (backlog item 13). `gh_*` columns
 * (E5-T7) are a separate migration — this one is everything the schema needs before GitHub
 * stats exist at all.
 *
 * `tool_file_media_id`: this app has no model implementing Spatie's HasMedia/
 * InteractsWithMedia — every "media collection" elsewhere (Skill/SocialProfile icons,
 * Project's og_image) is really one nullable FK column to `media`, not Spatie's native
 * relationship API. A single-file attachment here follows that same convention rather than
 * introducing a second, inconsistent media architecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary');
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->boolean('published')->default(false);
            $table->string('repo_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('tool_file_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('published');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
