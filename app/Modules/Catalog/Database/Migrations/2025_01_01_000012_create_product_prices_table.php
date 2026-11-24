<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the product_prices table — effective-dated price records.
     *
     * Each price record has:
     * - effective_from: when this price takes effect
     * - effective_to: when this price expires (null = currently active)
     *
     * When a new price is set, the previous price's effective_to is set
     * to the new price's effective_from. This preserves price history
     * for historical invoices (they reference the price at sale time).
     *
     * product_size_id is nullable — null means the price applies to all
     * sizes of the product. A non-null value means the price is
     * size-specific (overrides the product-level price).
     */
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_size_id')->nullable();
            $table->decimal('standard_price', 12, 2);
            $table->decimal('minimum_price', 12, 2);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->unsignedBigInteger('set_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_size_id')->references('id')->on('product_sizes')->cascadeOnDelete();
            $table->foreign('set_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['product_id', 'effective_from', 'effective_to'], 'idx_price_effective');
            $table->index('product_size_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
