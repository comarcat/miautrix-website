<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Found in review: "add the logos of the companies / education institutions" — companies
 * already had `logo_media_id` (E2-T2), but education had no equivalent column at all. Same
 * shape and same nullOnDelete reasoning as every other media FK on a publishable table
 * (§4 line 442: degrades gracefully to no logo, never blocks a Media delete on its own —
 * that guard is App\Models\Media's app-level MediaInUseException).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('education', function (Blueprint $table) {
            $table->foreignId('logo_media_id')->nullable()->after('field_of_study')
                ->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('education', function (Blueprint $table) {
            $table->dropForeign(['logo_media_id']);
            $table->dropColumn('logo_media_id');
        });
    }
};
