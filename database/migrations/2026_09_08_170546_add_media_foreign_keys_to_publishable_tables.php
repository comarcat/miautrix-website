<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * The media table didn't exist when profiles/companies/experiences/education/
     * certifications/projects were created (E2-T1..T3), so their avatar/logo/og_image/badge
     * columns were left as plain unsignedBigInteger with a comment promising the constraint
     * once media landed (E2-T4, here). §4 line 442: these all degrade gracefully via
     * nullOnDelete rather than blocking — the destructive-delete guard for a *published*
     * entity's media is App\Models\Media's app-level MediaInUseException instead.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->foreign('avatar_media_id')->references('id')->on('media')->nullOnDelete();
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('logo_media_id')->references('id')->on('media')->nullOnDelete();
        });
        Schema::table('experiences', function (Blueprint $table) {
            $table->foreign('og_image_id')->references('id')->on('media')->nullOnDelete();
        });
        Schema::table('education', function (Blueprint $table) {
            $table->foreign('og_image_id')->references('id')->on('media')->nullOnDelete();
        });
        Schema::table('certifications', function (Blueprint $table) {
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
            $table->foreign('og_image_id')->references('id')->on('media')->nullOnDelete();
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('og_image_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropForeign(['avatar_media_id']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['logo_media_id']);
        });
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['og_image_id']);
        });
        Schema::table('education', function (Blueprint $table) {
            $table->dropForeign(['og_image_id']);
        });
        Schema::table('certifications', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
            $table->dropForeign(['og_image_id']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['og_image_id']);
        });
    }
};
