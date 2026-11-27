<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the system_settings table — a generic key/value store for
     * runtime system configuration overrides (Session 2.6, barcode settings
     * integration; the Printer Settings UI in Session 2.7 writes rows here).
     *
     * Settings resolution order (implemented in LabelPrintingService):
     *   explicit method parameter → system_settings DB row → config default.
     *
     * Keys use dot-notation prefixes per settings group, e.g.:
     *   barcode.default_label_format
     *   barcode.default_printer
     *   barcode.show_price_on_label
     *   barcode.barcode_symbology
     *
     * Values are stored as strings; booleans are stored as '1'/'0' and
     * parsed with FILTER_VALIDATE_BOOL by the reading service.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
