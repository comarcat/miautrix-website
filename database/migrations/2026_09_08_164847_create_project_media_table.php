<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_media', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // media table doesn't exist until a later epic — no constrained() yet, the FK
            // constraint is added once the media migration lands.
            $table->unsignedBigInteger('media_id');
            $table->integer('sort_order')->default(0);

            $table->primary(['project_id', 'media_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_media');
    }
};
