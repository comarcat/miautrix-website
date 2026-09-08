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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('kind', ['resume', 'other']);
            // §4 line 442: media references degrade gracefully to no image/file — nullable
            // with nullOnDelete, guarded from the destructive side by App\Models\Media's
            // MediaInUseException when this document is published.
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->integer('download_count')->default(0);
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // resume download route lookup (blueprint §4 Indexes table).
            $table->index(['kind', 'published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
