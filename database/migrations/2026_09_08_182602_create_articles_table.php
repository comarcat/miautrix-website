<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pulled forward from E3-T6 (schema only — the Filament resource stays there): E2-T6
     * needs a real Article model for ArticlePolicy, and E2-T7 needs it to seed sample
     * articles. Blog, standalone, single-author (the one admin), no FK beyond implicit
     * ownership (§4).
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('excerpt');
            $table->longText('body'); // Filament RichEditor HTML
            $table->timestamp('published_at')->nullable(); // null = unpublished draft
            $table->boolean('featured')->default(false);
            // publishable-entity convention (.claude/rules/database.md)
            $table->string('slug')->unique();
            $table->boolean('published')->default(false)->index();
            $table->integer('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
