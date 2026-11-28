<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the sale_invoices table.
 *
 * One row per completed (or draft) POS sale. POS is online-mode only in
 * Phase 3 (offline is Phase 8); the `is_offline_sale` / `offline_uuid`
 * columns are forward-compatible and always false/null in Phase 3.
 *
 * Invoice status enum: draft, completed, void. Per WF-3 post-condition,
 * completed invoices are immutable — they cannot be cancelled or voided;
 * corrections go through the Returns module (Phase 5).
 *
 * `return_policy_snapshot` (JSONB) stores the active return policy at sale
 * time so the 7-day return window can be enforced later even if the policy
 * changes (used by Phase 5 Returns).
 *
 * Migration ordering (per docs/03-database-design.md §9.1): sale_invoices
 * is step 9. customer_id FK → customers (created in Session 3.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('salesperson_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('fiscal_year_id')->nullable();

            $table->string('sale_type', 20); // 'retail' | 'wholesale' (tag only — ADR-014)
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2); // subtotal - total_discount
            $table->decimal('total_paid', 12, 2);
            $table->string('status', 20)->default('draft'); // draft | completed | void

            $table->boolean('is_offline_sale')->default(false); // forward-compat (Phase 8)
            $table->uuid('offline_uuid')->nullable();           // forward-compat; unique partial below
            $table->timestamp('completed_at')->nullable();
            $table->json('return_policy_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('salesperson_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->nullOnDelete();

            $table->index(['branch_id', 'completed_at'], 'idx_invoices_branch_date');
            $table->index(['salesperson_id', 'completed_at'], 'idx_invoices_salesperson');
            $table->index('customer_id', 'idx_invoices_customer');
            $table->index('status');

            // Partial index: only completed invoices (the common report query).
            // The unique partial index on offline_uuid uses WHERE NOT NULL so
            // multiple on-line (null uuid) invoices don't collide.
            $table->unique('offline_uuid', 'uniq_invoices_offline_uuid');
        });

        // Add a partial unique index on offline_uuid only where NOT NULL
        // (PostgreSQL supports partial unique indexes). On-line sales have a
        // NULL uuid; only offline sales (one per uuid) must be unique.
        DB::statement(
            'CREATE UNIQUE INDEX uniq_invoices_offline_uuid_notnull '
            .'ON sale_invoices (offline_uuid) WHERE offline_uuid IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_invoices');
    }
};
