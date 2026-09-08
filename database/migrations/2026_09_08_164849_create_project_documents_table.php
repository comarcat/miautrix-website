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
        Schema::create('project_documents', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // documents table doesn't exist until a later epic — no constrained() yet, the FK
            // constraint is added once the documents migration lands.
            $table->unsignedBigInteger('document_id');

            $table->primary(['project_id', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
