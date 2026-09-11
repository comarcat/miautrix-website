<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E4-T3, §9 step 28) — supplementary downloadable files attached to a project
 * (one-sheets, source archives, reports). Same composite-PK-pivot shape as project_media/
 * project_documents, plus an optional admin-editable `label` and `sort_order`. The media
 * table (and its FK target) already exist by this point, unlike project_media's two-step
 * migration history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_files', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->integer('sort_order')->default(0);

            $table->primary(['project_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_files');
    }
};
