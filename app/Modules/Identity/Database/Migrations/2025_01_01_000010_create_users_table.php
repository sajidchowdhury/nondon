<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: This migration references the 'branches' table which must exist.
     * The branches migration (Organization module, timestamp 000001) runs before
     * this one (Identity module, timestamp 000010).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('email', 255)->nullable();
            $table->string('password_hash', 255);
            $table->string('full_name');
            $table->foreignId('assigned_branch_id')->nullable()
                ->constrained('branches')->nullOnDelete();
            $table->boolean('is_online_channel')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('force_password_change')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            // active_role_id references Spatie's roles table.
            // Using unsignedBigInteger instead of foreignId to avoid migration
            // ordering dependency on Spatie's published migrations.
            // The relationship is enforced at the Eloquent level.
            $table->unsignedBigInteger('active_role_id')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('assigned_branch_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
