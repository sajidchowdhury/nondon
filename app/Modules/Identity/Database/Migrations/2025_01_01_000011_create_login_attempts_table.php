<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100);
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('success');
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['username', 'attempted_at'], 'idx_login_attempts_username');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
