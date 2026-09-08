<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->text('description');
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->string('repo_url')->nullable();
            $table->string('live_url')->nullable();
            // publishable-entity convention (.claude/rules/database.md)
            $table->string('slug')->unique();
            $table->boolean('published')->default(false)->index();
            $table->boolean('featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            // media table doesn't exist until a later epic — no constrained() yet, the FK
            // constraint is added once the media migration lands.
            $table->unsignedBigInteger('og_image_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // category filter page (blueprint §4 Indexes table).
            $table->index('project_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
