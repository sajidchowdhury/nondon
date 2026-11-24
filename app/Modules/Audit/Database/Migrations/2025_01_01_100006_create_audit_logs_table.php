<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the audit_logs table — the append-only ledger of all
     * meaningful business actions in the system.
     *
     * This table is NEVER updated or deleted from. Corrections are
     * new audit entries. This is a core data integrity principle.
     *
     * Note: Monthly partitioning will be configured in Phase 10 when
     * volume grows beyond ~10M rows.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('action', 100);
            $table->string('category', 50);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->jsonb('previous_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at')->useCurrent();

            // Indexes for common query patterns
            $table->index(['user_id', 'performed_at'], 'idx_audit_user');
            $table->index(['branch_id', 'performed_at'], 'idx_audit_branch_date');
            $table->index(['reference_type', 'reference_id'], 'idx_audit_reference');
            $table->index(['action', 'performed_at'], 'idx_audit_action');
            $table->index(['category', 'performed_at'], 'idx_audit_category');
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
