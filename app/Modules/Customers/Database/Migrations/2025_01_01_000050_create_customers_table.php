<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the customers table.
 *
 * Customers are the primary actor in the Sales module (Phase 3). Phone is the
 * mandatory primary identifier (BRD §3.2, Security §7.4); name is optional.
 * Customers are NEVER deleted — duplicates are merged by marking the duplicate
 * inactive and redirecting via merged_into_id (WF-25, Phase 3 Session 3.10).
 *
 * Customers are not branch-scoped at the row level (a customer may purchase at
 * any branch), but PII visibility is enforced per role at the service/policy
 * layer: admin sees all; branch supervisor/salesperson see customers that have
 * a sale at their branch or are walk-in with no sales yet; online team sees
 * only is_online_customer records.
 *
 * Migration ordering (per docs/03-database-design.md §9.1): customers is step 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Phone is the primary identifier — mandatory and unique.
            // One phone per customer (BRD: single phone, no alternate numbers).
            $table->string('phone', 50)->unique();

            // Name is optional (BRD §3.2: "Customer creation: phone mandatory,
            // name optional"). Walk-in customers may be created with phone only.
            $table->string('name', 255)->nullable();

            // Delivery/postal address (primarily for online customers; collected
            // for walk-in customers only if provided).
            $table->text('address')->nullable();

            // Distinguishes online-channel customers from walk-in retail
            // customers. Drives PII scope for online_team/online_supervisor.
            $table->boolean('is_online_customer')->default(false);

            // Customers are never deleted. After a merge (WF-25) the duplicate
            // record is marked inactive and points to the primary via
            // merged_into_id. is_active = false excludes the record from
            // customer lookup (POS findOrCreateByPhone returns the primary).
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('merged_into_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('merged_into_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            // Fast phone lookup is the primary access path (POS customer search,
            // duplicate detection on create).
            $table->index('phone');
            $table->index('merged_into_id');
            $table->index('is_active');
            $table->index('is_online_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
