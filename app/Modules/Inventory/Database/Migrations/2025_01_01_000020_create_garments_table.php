<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the garments table — the central entity of the system.
     * One row per physical garment with immutable identity.
     *
     * Three identity layers:
     * 1. internal_id (UUID) — immutable, globally unique, never reused
     * 2. barcode — permanent, globally unique, never reused, independent of fiscal year
     * 3. fiscal_year_serial — format YY-NNNNN, restarts each fiscal year
     *
     * Unique constraints:
     * - internal_id (UUID)
     * - barcode
     * - (fiscal_year_id, fiscal_year_serial) — serial is unique within a fiscal year
     *
     * purchase_cost is restricted to Main Branch Admin only (hidden at model level).
     */
    public function up(): void
    {
        Schema::create('garments', function (Blueprint $table) {
            $table->id();
            $table->uuid('internal_id')->unique();
            $table->string('barcode', 100)->unique();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('fiscal_year_serial', 20);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_size_id')->nullable();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('purchase_item_id')->nullable();
            $table->unsignedBigInteger('goods_receipt_id')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->string('current_status', 30)->default('available');
            $table->unsignedBigInteger('current_branch_id')->nullable();
            $table->unsignedBigInteger('current_location_id')->nullable();
            $table->boolean('is_clearance')->default(false);
            $table->timestamps();

            // Foreign keys
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_size_id')->references('id')->on('product_sizes')->nullOnDelete();
            $table->foreign('current_branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('current_location_id')->references('id')->on('stock_locations')->nullOnDelete();

            // Unique constraint: serial is unique within a fiscal year
            $table->unique(['fiscal_year_id', 'fiscal_year_serial']);

            // Indexes for common queries
            $table->index('barcode');
            $table->index(['product_id', 'product_size_id'], 'idx_garments_product_size');
            $table->index(['current_status', 'current_branch_id'], 'idx_garments_status_branch');
            $table->index(['fiscal_year_id', 'fiscal_year_serial'], 'idx_garments_serial');

            // Partial index: available garments by product/size/branch (common POS query)
            $table->index(['product_id', 'product_size_id', 'current_branch_id'], 'idx_garments_available')
                  ->where('current_status', 'available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garments');
    }
};
