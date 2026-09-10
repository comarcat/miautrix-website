<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E2-T6, §9 step 14) — ordered sections for the /connect page. Each group carries
 * an admin-editable `heading` (shown above its links) and an optional `intro_text`.
 * `social_profiles.group_id` (next migration) points here; ungrouped rows render under "Other".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_profile_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('heading');
            $table->text('intro_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_profile_groups');
    }
};
