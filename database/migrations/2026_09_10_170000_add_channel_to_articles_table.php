<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E4-T7, §9 step 32) — separates the professional blog from the future "Life"
 * blog (E4-T8) at the data level. Defaults every existing row to `professional` so nothing
 * already published silently moves channel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('channel', 20)->default('professional')->index();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('channel');
        });
    }
};
