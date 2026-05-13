<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add payments_module_id foreign key and unique constraint for idempotency protection
     * This prevents duplicate advance utilization on invoice payment retry
     */
    public function up(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Add payments_module_id foreign key
            $table->foreignId('payments_module_id')->nullable()->after('purchase_invoice_id')->constrained()->nullOnDelete();
            
            // Add unique constraint for idempotency protection
            // Prevents duplicate utilization for same invoice + payment combination
            $table->unique(['purchase_invoice_id', 'payments_module_id'], 'unique_invoice_payment_utilization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Drop unique constraint
            $table->dropUnique('unique_invoice_payment_utilization');
            
            // Drop foreign key and column
            $table->dropForeign(['payments_module_id']);
            $table->dropColumn('payments_module_id');
        });
    }
};
