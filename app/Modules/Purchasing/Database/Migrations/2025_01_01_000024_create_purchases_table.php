<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the purchases table — purchase order master records.
     *
     * Purchases are admin-only (per BRD §4.1). The status state machine is:
     *   draft → confirmed → received | partially_received
     *   draft | confirmed → cancelled
     *
     * Purchase cost data (total_cost) is SENSITIVE — restricted to Main Branch
     * Admin only. The Purchase model enforces this via $hidden + withTotalCost().
     *
     * Each purchase is tied to a fiscal year (for serial context) and a supplier.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 50)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('fiscal_year_id');
            $table->date('purchase_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['supplier_id', 'purchase_date']);
            $table->index('status');
            $table->index('fiscal_year_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
