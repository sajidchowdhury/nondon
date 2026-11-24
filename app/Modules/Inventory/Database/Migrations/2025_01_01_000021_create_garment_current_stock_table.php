<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the garment_current_stock table — a denormalized projection
     * of the current state of each garment, maintained alongside the
     * append-only inventory_movements ledger for fast reads.
     *
     * One row per garment. Updated transactionally whenever a garment's
     * status or location changes. Periodic reconciliation verifies
     * projection against the ledger.
     */
    public function up(): void
    {
        Schema::create('garment_current_stock', function (Blueprint $table) {
            $table->unsignedBigInteger('garment_id')->primary();
            $table->string('current_status', 30);
            $table->unsignedBigInteger('current_branch_id')->nullable();
            $table->unsignedBigInteger('current_location_id')->nullable();
            $table->string('reservation_reference_type', 30)->nullable();
            $table->unsignedBigInteger('reservation_reference_id')->nullable();
            $table->unsignedBigInteger('last_movement_id')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('garment_id')->references('id')->on('garments')->cascadeOnDelete();
            $table->foreign('current_branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('current_location_id')->references('id')->on('stock_locations')->nullOnDelete();

            $table->index(['current_status', 'current_branch_id'], 'idx_gcs_status_branch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garment_current_stock');
    }
};
