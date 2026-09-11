<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E2-T6, §9 step 14) — nullable FK from a social profile to its /connect section.
 * `nullOnDelete`: deleting a group never deletes its links, it just ungroups them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_profiles', function (Blueprint $table): void {
            $table->foreignId('group_id')
                ->nullable()
                ->after('profile_id')
                ->constrained('social_profile_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_profiles', function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
