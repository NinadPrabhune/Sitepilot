<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add NOT NULL constraint for payment_number to prevent direct inserts without number generation.
     * This protects against bypassing model events (e.g., DB::table()->insert()).
     */
    public function up(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            // Add NOT NULL constraint for payment_number
            // This ensures all inserts must include a payment_number
            $table->string('payment_number')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            // Revert to nullable
            $table->string('payment_number')->nullable()->change();
        });
    }
};
