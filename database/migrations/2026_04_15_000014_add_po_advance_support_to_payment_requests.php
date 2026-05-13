<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add PO advance support to payment_requests table
     * - Add po_id column (nullable, foreign key to purchase_orders)
     * - Add type column (VARCHAR(50), DEFAULT 'invoice_payment')
     * - Keep purchase_invoice_id as NOT NULL for backward compatibility
     * - Add composite indexes for performance
     * - Migrate existing records to have type='invoice_payment'
     */
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            // Add po_id column (nullable, foreign key to purchase_orders)
            $table->foreignId('po_id')->nullable()->after('id')->constrained('purchase_orders')->nullOnDelete();
            
            // Add type column with default value
            $table->string('type', 50)->default('invoice_payment')->after('po_id');
            
            // Add composite indexes for performance
            $table->index(['po_id', 'type']);
            $table->index(['purchase_invoice_id', 'type']);
            $table->index(['type', 'status']);
        });
        
        // Migrate existing records to have type='invoice_payment'
        DB::statement("UPDATE payment_requests SET type = 'invoice_payment' WHERE type IS NULL OR type = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['po_id', 'type']);
            $table->dropIndex(['purchase_invoice_id', 'type']);
            $table->dropIndex(['type', 'status']);
            
            // Drop columns
            $table->dropForeign(['po_id']);
            $table->dropColumn('po_id');
            $table->dropColumn('type');
        });
    }
};
