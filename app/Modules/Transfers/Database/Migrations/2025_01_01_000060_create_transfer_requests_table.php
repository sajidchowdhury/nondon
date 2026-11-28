<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the transfer_requests table.
 *
 * Inter-branch transfer requests (WF-6 + WF-7). One row per request, with
 * the 8-value status lifecycle: pending → approved/rejected → dispatched →
 * received/partially_received/discrepancy (or cancelled from pending/approved).
 *
 * Approval is modelled as status transitions + approved_by/approved_at columns
 * — there is NO separate transfer_approvals table (per doc 03 §3.6).
 *
 * `online_order_id` is nullable and has NO inline FK — it's a forward-compat
 * pointer for Phase 4 (Online Orders), added now so Phase 4 doesn't need to
 * alter this table. The FK will be added when online_orders is created.
 *
 * Migration ordering (per docs/03-database-design.md §9.1): transfer_requests
 * is step 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 50)->unique(); // TR-25-00001

            $table->unsignedBigInteger('from_branch_id');
            $table->unsignedBigInteger('to_branch_id');

            $table->string('status', 20)->default('pending');
            // CHECK constraint for the 8 status values (enforced at DB level).
            // MySQL/SQLite ignore the check; PostgreSQL enforces it.

            $table->unsignedBigInteger('requested_by');
            $table->timestamp('requested_at')->useCurrent();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();

            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();

            // Forward-compat for Phase 4 (Online Orders). No inline FK — added
            // when online_orders is created (Phase 4).
            $table->unsignedBigInteger('online_order_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('from_branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('to_branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('dispatched_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index(['from_branch_id', 'to_branch_id'], 'idx_transfers_branches');
            $table->index(['from_branch_id', 'status'], 'idx_transfers_from_status');
            $table->index(['to_branch_id', 'status'], 'idx_transfers_to_status');
        });

        // PostgreSQL CHECK constraint for the 8 status values.
        DB::statement(
            'ALTER TABLE transfer_requests ADD CONSTRAINT transfer_requests_status_check '
            ."CHECK (status IN ('pending','approved','rejected','dispatched','received','partially_received','cancelled','discrepancy'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};
