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
     * media_id is part of project_media's composite primary key (project_id, media_id), so it
     * can't be nulled like the other media references — cascadeOnDelete instead: deleting the
     * media row just drops the pivot row, the project itself is unaffected.
     */
    public function up(): void
    {
        Schema::table('project_media', function (Blueprint $table) {
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_media', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
        });
    }
};
