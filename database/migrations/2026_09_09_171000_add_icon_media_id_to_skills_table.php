<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Found in review: "the icons that I mentioned is for the skills like Windows Server -> Icon
 * of Windows Server, Sharepoint -> Icon of the logo of SharePoint" — skills had no icon
 * column at all. Same shape as every other media FK on this schema; skills have no
 * `published` gate of their own (Skill::scopePublished() doesn't exist — a skill is public
 * as soon as it exists), so nullOnDelete is the only lifecycle rule needed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->foreignId('icon_media_id')->nullable()->after('name')
                ->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropForeign(['icon_media_id']);
            $table->dropColumn('icon_media_id');
        });
    }
};
