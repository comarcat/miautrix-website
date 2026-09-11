<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (E6-T1, §9 step 44) — visitor-submitted endorsements (backlog item 15). Invisible
 * until the owner approves one in Filament: `status` defaults `pending`, and the public
 * /endorsements page only ever reads the `approved` scope. `contact_type`/`status` are
 * validated in code (this repo's enum-in-code pattern — see Article::channel, Theme's
 * is_default, etc.), not a DB enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('role')->nullable();
            $table->string('contact_type');
            $table->string('contact_value');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('company_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
