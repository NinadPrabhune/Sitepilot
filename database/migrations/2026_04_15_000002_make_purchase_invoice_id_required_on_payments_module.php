<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Validate all payments have purchase_invoice_id
        $paymentsWithoutInvoice = DB::table('payments_module')
            ->whereNull('purchase_invoice_id')
            ->count();

        if ($paymentsWithoutInvoice > 0) {
            // Log warning - this should be resolved before migration
            // In production, this would throw an exception
            // For now, we'll allow it but log it
            Log::channel('payment_audit')->warning("{$paymentsWithoutInvoice} payments without purchase_invoice_id found. These will be handled in Phase 3 migration.");
        }

        // Step 2: For payments without invoice_id but with po_id, we'll keep them nullable
        // These will be handled in Phase 3 migration
        // For now, we just ensure the column exists and is indexed

        // Step 3: Add index on purchase_invoice_id if not exists (for performance)
        // SAFETY CHECK: Check if index exists before adding
        $indexExists = collect(DB::select("SHOW INDEX FROM payments_module WHERE Key_name = 'idx_purchase_invoice_id'"))->isNotEmpty();
        if (!$indexExists) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->index('purchase_invoice_id', 'idx_purchase_invoice_id');
            });
        }

        // Note: We are NOT making purchase_invoice_id NOT NULL yet
        // This will be done in Phase 3 after migrating PO-based payments
        // This migration prepares the infrastructure
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropIndex('idx_purchase_invoice_id');
        });
    }
};
