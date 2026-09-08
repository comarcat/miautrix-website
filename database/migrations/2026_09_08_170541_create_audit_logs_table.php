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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // append-only: history must survive a deleted user (§4).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address')->nullable();
            // no updated_at column at all — append-only (§4). App\Models\AuditLog sets
            // const UPDATED_AT = null so Eloquent never tries to write one.
            $table->timestamp('created_at');

            // "history for this record" admin view (blueprint §4 Indexes table).
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
