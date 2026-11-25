<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the inventory_movements table — the append-only ledger
     * of all stock movements in the system.
     *
     * This table is NEVER updated or deleted from. Every stock change
     * creates a new row. Corrections are new movement records.
     *
     * Note: Monthly partitioning will be configured in Phase 10 when
     * volume grows beyond ~10M rows.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('garment_id');
            $table->string('movement_type', 30);
            $table->unsignedBigInteger('from_branch_id')->nullable();
            $table->unsignedBigInteger('to_branch_id')->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('performed_by');
            $table->timestamp('performed_at')->useCurrent();
            $table->text('notes')->nullable();

            // Foreign keys
            $table->foreign('garment_id')->references('id')->on('garments')->cascadeOnDelete();
            $table->foreign('from_branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('to_branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('from_location_id')->references('id')->on('stock_locations')->nullOnDelete();
            $table->foreign('to_location_id')->references('id')->on('stock_locations')->nullOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->restrictOnDelete();

            // Indexes for common query patterns
            $table->index(['garment_id', 'performed_at'], 'idx_movements_garment');
            $table->index(['movement_type', 'performed_at'], 'idx_movements_type');
            $table->index(['reference_type', 'reference_id'], 'idx_movements_reference');
            $table->index(['from_branch_id', 'performed_at'], 'idx_movements_from_branch');
            $table->index(['to_branch_id', 'performed_at'], 'idx_movements_to_branch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
