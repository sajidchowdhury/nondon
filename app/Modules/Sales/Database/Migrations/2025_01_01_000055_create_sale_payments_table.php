<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the sale_payments table.
 *
 * Multiple payment rows per invoice are allowed (cash + bKash in one sale,
 * per WF-3 step 5c). `payment_method` references payment_methods.name (soft
 * FK; validated in SaleService). `reference_number` is required for methods
 * where payment_methods.requires_reference = true (bkash, nagad, bank, card).
 *
 * The Phase 6 Cash module will add a listener on SaleCompleted that posts a
 * cash-transaction row for cash payments to the salesperson's cash account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_invoice_id');
            $table->string('payment_method', 30); // → payment_methods.name (soft FK)
            $table->decimal('amount', 12, 2);
            $table->string('reference_number', 100)->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('sale_invoice_id')->references('id')->on('sale_invoices')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('sale_invoice_id');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
