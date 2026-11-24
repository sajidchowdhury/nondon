<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the user_permissions table for tracking per-user permission
     * grants and revokes. This is separate from Spatie's model_has_permissions
     * because we need to distinguish between grants (true) and revokes (false).
     *
     * Effective Permission = (Role Permissions UNION User Grants)
     *                       MINUS User Revokes
     *                       WITH Branch Scope Enforcement
     *                       WITH Sensitive Data Restrictions (always applied)
     */
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('granted')->default(true); // true = grant, false = revoke
            $table->unsignedBigInteger('granted_by')->nullable(); // admin who made the change
            $table->timestamp('granted_at')->useCurrent();
            $table->string('reason')->nullable(); // optional reason for the override

            $table->unique(['user_id', 'permission_id']); // one entry per user-permission pair

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'granted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
