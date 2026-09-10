<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E2-T4, §9 step 12) — guarantee `articles` and `projects` each carry a nullable
 * `og_image_id` FK to `media`.
 *
 * Phase 1 already added these columns and constraints
 * (2026_09_08_170546_add_media_foreign_keys_to_publishable_tables), so on the live database
 * this migration is a verified no-op: every branch is guarded by `Schema::hasColumn` and
 * only fires where the column is genuinely absent. It exists so a fresh checkout — or any
 * environment where the Phase 1 migration was squashed — still ends up with the column the
 * OG-image pipeline depends on.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['articles', 'projects'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'og_image_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t): void {
                $t->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: this migration never owns the column on the live database
        // (Phase 1 does), so dropping it here would corrupt a rollback. A fresh checkout
        // that gained the column here can drop it by rolling back Phase 1's own migration.
    }
};
