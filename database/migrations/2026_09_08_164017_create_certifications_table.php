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
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('issuer');
            $table->string('credential_id')->nullable();
            $table->string('credential_url');
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            // badge image — media table doesn't exist until a later epic.
            $table->unsignedBigInteger('media_id')->nullable();
            // publishable-entity convention (.claude/rules/database.md)
            $table->string('slug')->unique();
            $table->boolean('published')->default(false)->index();
            $table->boolean('featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->unsignedBigInteger('og_image_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
