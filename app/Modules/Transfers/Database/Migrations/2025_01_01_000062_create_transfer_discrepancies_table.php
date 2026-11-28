<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the transfer_discrepancies table.
 *
 * Records shortages/excess/wrong-item discrepancies at receiving (WF-7 step
 * 2e). The `missing_garment_ids` and `excess_garment_ids` are JSON arrays of
 * garment ids (per the plan's decision — JSON for portability across DBs,
 * though PostgreSQL arrays would also work; JSON keeps it consistent with the
 * rest of the schema and the Laravel JSON cast).
 *
 * Per-garment progress is tracked via transfer_request_items.dispatched/
 * received flags; this table is the discrepancy header with the specific
 * garment ids that didn't match. Full investigation/disposition is Phase 7
 * (Stock Control) — Phase 3 only records the discrepancy and surfaces it.
 *
 * Migration ordering: step 8 (after transfer_request_items).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_request_id');

            $table->string('discrepancy_type', 20); // 'shortage' | 'excess' | 'wrong_item'
            $table->string('reason', 255)->nullable();

            // JSON arrays of garment ids (missing = expected-but-not-received;
            // excess = received-but-not-expected).
            $table->json('missing_garment_ids')->nullable();
            $table->json('excess_garment_ids')->nullable();

            $table->unsignedBigInteger('investigated_by')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('status', 20)->default('open'); // 'open' | 'investigating' | 'resolved'
            $table->timestamps();

            $table->foreign('transfer_request_id')->references('id')->on('transfer_requests')->cascadeOnDelete();
            $table->foreign('investigated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();

            $table->index('transfer_request_id');
            $table->index('status');
        });

        DB::statement(
            'ALTER TABLE transfer_discrepancies ADD CONSTRAINT transfer_discrepancies_type_check '
            ."CHECK (discrepancy_type IN ('shortage','excess','wrong_item'))"
        );
        DB::statement(
            'ALTER TABLE transfer_discrepancies ADD CONSTRAINT transfer_discrepancies_status_check '
            ."CHECK (status IN ('open','investigating','resolved'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_discrepancies');
    }
};
