<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Phase 8 Final Cleanup:
     * - Remove snapshot tables created during Phase 3
     * - Remove deprecated model files
     * - Clean up any remaining deprecated code references
     */
    public function up(): void
    {
        // Drop Phase 3 snapshot tables (after 30+ days of verification)
        Schema::dropIfExists('payment_migration_snapshot');
        Schema::dropIfExists('ledger_balance_snapshot');
        Schema::dropIfExists('payment_module_allocations_backup');

        Log::channel('payment_audit')->info('Phase 8: Dropped Phase 3 snapshot tables');

        // Note: Model file deletion (PaymentModuleAllocation.php) must be done manually
        // as migrations cannot delete files
        // This should be done after confirming no code references remain

        Log::channel('payment_audit')->info('Phase 8: Final cleanup completed', [
            'manual_steps_required' => [
                'Delete app/Models/PaymentModuleAllocation.php',
                'Remove deprecated methods from PurchaseOrder.php',
                'Remove deprecated methods from PaymentsModule.php',
                'Update UI views to use invoicing_status',
                'Run full test suite',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate snapshot tables for rollback
        DB::statement("
            CREATE TABLE IF NOT EXISTS payment_migration_snapshot (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_id INT,
                old_payment_type VARCHAR(50),
                new_payment_type VARCHAR(50),
                old_purchase_invoice_id INT,
                new_purchase_invoice_id INT,
                purchase_order_id INT,
                allocation_id INT,
                allocated_amount DECIMAL(15,2),
                migration_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS ledger_balance_snapshot (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_id INT,
                site_id INT,
                balance_before DECIMAL(15,2),
                balance_after DECIMAL(15,2),
                difference DECIMAL(15,2),
                recalculation_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS payment_module_allocations_backup (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_module_id INT NOT NULL,
                purchase_invoice_id INT NULL,
                purchase_order_id INT NULL,
                allocated_amount DECIMAL(15,2) NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            )
        ");

        Log::channel('payment_audit')->info('Phase 8: Restored snapshot tables');
    }
};
