<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requested in production review: "add to the social profiles, a field to check if it
 * should appear on the footer of the website" plus "a new section to publish all social
 * profiles there with their logos near to the links".
 *
 * show_in_footer defaults to true — the site currently has exactly one real SocialProfile
 * (LinkedIn), and the point of adding this toggle was to control what's ALREADY showing in
 * the footer going forward, not to blank the footer out the instant this migration runs.
 * icon_media_id is nullable/nullOnDelete, same shape as every other media FK on this schema
 * (Company/Education/Skill's own logo/icon columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_profiles', function (Blueprint $table) {
            $table->boolean('show_in_footer')->default(true)->after('url');
            $table->foreignId('icon_media_id')->nullable()->after('show_in_footer')
                ->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_profiles', function (Blueprint $table) {
            $table->dropForeign(['icon_media_id']);
            $table->dropColumn(['show_in_footer', 'icon_media_id']);
        });
    }
};
