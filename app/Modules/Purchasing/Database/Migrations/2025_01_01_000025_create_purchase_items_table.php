<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the purchase_items table — line items within a purchase order.
     *
     * Each line item specifies: product, optional size, ordered quantity,
     * received quantity (incremented during goods receiving in Session 2.4),
     * and unit cost.
     *
     * unit_cost is SENSITIVE — restricted to Main Branch Admin only.
     * The PurchaseItem model enforces this via $hidden + withUnitCost().
     *
     * ON DELETE CASCADE from purchase: if a draft purchase is deleted
     * (not currently exposed in UI but supported at model level), its items
     * are removed too. Confirmed purchases are never deleted.
     */
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_size_id')->nullable();
            $table->integer('ordered_quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_size_id')->references('id')->on('product_sizes')->nullOnDelete();

            $table->index('purchase_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
