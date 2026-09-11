<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E3-T1, §9 step 17) — admin-managed themes. `technical` and `matrix` become data
 * rows (seeded by ThemeSeeder with empty `tokens`, so they render byte-identically to the
 * hard-coded Phase 1 versions); special-event themes are new rows with JSON token overrides
 * and an optional active window. Reads happen behind the `site.themes.dynamic` flag until
 * E3-T9 flips it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->json('tokens')->default('{}');
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->boolean('shows_life_blog')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('enabled');
            $table->index('active_from');
            $table->index('active_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
