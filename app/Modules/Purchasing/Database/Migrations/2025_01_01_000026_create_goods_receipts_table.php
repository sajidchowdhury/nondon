<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the goods_receipts table — records each goods receiving event.
     *
     * One purchase can have multiple goods receipts (for partial receipts).
     * Each receipt records the total received quantity and a unique receipt
     * number (format: GR-{YY}-{NNNNN}).
     *
     * The individual received garments are tracked via the `garments` table
     * (each garment has a `goods_receipt_id` FK set during receiving).
     *
     * Receipt number format: GR-{fiscal_year_prefix}-{NNNNN}
     * e.g., GR-25-00001
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->string('receipt_number', 50)->unique();
            $table->date('received_date');
            $table->unsignedBigInteger('received_by');
            $table->integer('total_received_quantity');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('purchase_id')->references('id')->on('purchases')->restrictOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();

            $table->index('purchase_id');
            $table->index('received_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
