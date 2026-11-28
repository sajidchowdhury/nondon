<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the transfer_request_items table.
 *
 * One row per garment in a transfer request (garment-level granularity per
 * doc 03 §3.6). The dispatched/received flags track per-garment progress
 * through the lifecycle.
 *
 * Migration ordering: step 8 (after transfer_requests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_request_id');
            $table->unsignedBigInteger('garment_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_size_id')->nullable();
            $table->boolean('dispatched')->default(false);
            $table->boolean('received')->default(false);
            $table->timestamps();

            $table->foreign('transfer_request_id')->references('id')->on('transfer_requests')->cascadeOnDelete();
            $table->foreign('garment_id')->references('id')->on('garments')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_size_id')->references('id')->on('product_sizes')->nullOnDelete();

            $table->index('transfer_request_id');
            $table->index('garment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_request_items');
    }
};
