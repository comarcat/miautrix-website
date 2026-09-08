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
        Schema::create('software_projects', function (Blueprint $table) {
            $table->id();
            // 1:1 extension table on projects, not single-table-inheritance (§4).
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('language_primary')->nullable();
            $table->text('architecture_notes')->nullable();
            $table->text('deployment_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('software_projects');
    }
};
