<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique(); // FY 25_26
            $table->date('start_date');
            $table->date('end_date');
            $table->string('serial_prefix', 2); // 25
            $table->integer('current_serial_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique('start_date');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
