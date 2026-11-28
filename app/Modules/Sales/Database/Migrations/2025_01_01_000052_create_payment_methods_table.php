<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the payment_methods table.
 *
 * The configurable list of payment methods accepted at POS: cash, bKash,
 * Nagad, bank_transfer, card (BRD §3.7, doc 03 §3.10). POS sale_payments
 * reference payment_methods.name (soft FK, validated in SaleService).
 *
 * `requires_reference` flags methods that need a transaction id (bkash,
 * nagad, bank_transfer, card). `is_cash_method` flags the cash method so
 * the Phase 6 cash-closing calculation can sum only cash payments.
 *
 * Ownership note: payment_methods is documented under the Cash module
 * (Phase 6) but created here because POS needs the method list immediately.
 * Phase 6 will own the cash-account ledger that posts against these methods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // 'cash', 'bkash', 'nagad', 'bank_transfer', 'card'
            $table->string('display_name', 100);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('is_cash_method')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
