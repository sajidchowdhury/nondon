<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the suppliers table — supplier master data for the Purchasing module.
     *
     * Supplier information is SENSITIVE DATA (per BRD §4.1 and Security §4.3):
     * Only Main Branch Admin can view supplier details, contacts, and
     * outstanding balances. The Supplier model enforces this via $hidden +
     * withSensitiveInfo() accessor pattern (mirroring Garment::withPurchaseCost()).
     *
     * Suppliers are soft-deactivated via is_active, never hard-deleted, to
     * preserve referential integrity with historical purchases.
     *
     * outstanding_balance is read-only in Phase 2; it is mutated only in
     * Phase 6 (Supplier Payments module) when payments are confirmed.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('contact_person', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index('is_active');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
