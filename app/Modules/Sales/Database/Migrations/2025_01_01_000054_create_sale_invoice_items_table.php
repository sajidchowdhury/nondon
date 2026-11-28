<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the sale_invoice_items table.
 *
 * One row per garment sold (garment-level granularity per doc 03 §3.7).
 * Stores the standard_price snapshot at sale time plus the applied_discount
 * and final_price so a historical invoice retains its original pricing
 * regardless of later price changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_invoice_id');
            $table->unsignedBigInteger('garment_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_size_id')->nullable();

            $table->decimal('standard_price', 12, 2);
            $table->decimal('applied_discount', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2); // standard_price - applied_discount
            $table->timestamps();

            $table->foreign('sale_invoice_id')->references('id')->on('sale_invoices')->cascadeOnDelete();
            $table->foreign('garment_id')->references('id')->on('garments')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_size_id')->references('id')->on('product_sizes')->nullOnDelete();

            $table->index('sale_invoice_id');
            $table->index('garment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_invoice_items');
    }
};
