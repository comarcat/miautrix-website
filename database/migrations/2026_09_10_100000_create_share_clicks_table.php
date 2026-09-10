<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E2-T1) — the first-party click log behind the blog share links. When a reader
 * clicks a share control they hit `/s/{network}/{type}/{id}`, which records one row here and
 * then 302s to the real network share URL. There is no tracking script anywhere; this table
 * IS the analytics for item 1.
 *
 * Append-only: `created_at` only, no `updated_at`, no soft delete, and deliberately no
 * foreign key to the shared subject — `subject_id` is a plain integer because the subject
 * can be an article today and a Life post or a project later, and a click log should not
 * cascade-delete or block a delete of the thing it references.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('network', 32);
            $table->string('type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('referrer')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_clicks');
    }
};
