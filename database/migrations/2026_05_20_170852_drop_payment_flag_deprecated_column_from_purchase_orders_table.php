<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the deprecated column if it exists
        if (Schema::hasColumn('purchase_orders', 'payment_flag_deprecated')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('payment_flag_deprecated');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This migration is destructive and cannot be reversed safely
        // The column data has been migrated to invoiced_status
    }
};
