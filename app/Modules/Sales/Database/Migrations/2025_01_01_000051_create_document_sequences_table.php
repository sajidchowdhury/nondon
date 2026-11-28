<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the document_sequences table.
 *
 * A dedicated per-fiscal-year, per-document-type counter for high-concurrency
 * document-number generation. Used by SaleNumberService (and later
 * TransferNumberService) to produce concurrency-safe sequential numbers
 * (INV-25-00001, TR-25-00001) via pg_advisory_xact_lock.
 *
 * The count+1 shortcut used by Phase 2 ReceivingService is safe only for
 * low-concurrency admin operations; POS sales are high-concurrency (doc 06
 * §1.2: 5 simultaneous checkouts per branch), so a real counter with an
 * advisory lock is required here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('doc_type', 30); // 'sale_invoice', 'transfer_request', ...
            $table->unsignedInteger('current_seq')->default(0);
            $table->timestamps();

            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->cascadeOnDelete();
            $table->unique(['fiscal_year_id', 'doc_type'], 'doc_seq_fy_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
