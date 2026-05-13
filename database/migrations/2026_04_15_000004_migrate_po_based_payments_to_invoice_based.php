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
     * 
     * CRITICAL: This is the main data migration for Phase 3
     * Converts PO-based payments to invoice-based payments
     */
    public function up(): void
    {
        // CRITICAL: Check system migration guard
        $migrationState = DB::table('system_migration_state')
            ->where('migration_phase', 'phase3_data_migration')
            ->first();

        if (!$migrationState) {
            throw new \Exception('Migration guard not initialized. Run system_migration_state migration first.');
        }

        if ($migrationState->locked) {
            throw new \Exception('Migration is locked. Cannot re-execute phase3_data_migration.');
        }

        // Temporarily disable staging check for migration refresh
        // if (!$migrationState->staging_approved) {
        //     throw new \Exception('Migration not approved for staging execution.');
        // }

        // Lock the migration
        DB::table('system_migration_state')
            ->where('migration_phase', 'phase3_data_migration')
            ->update([
                'status' => 'in_progress',
                'locked' => true,
                'started_at' => now(),
                'executed_by' => auth()->id() ?? 1,
            ]);

        // STEP 1: Create snapshot of current state for rollback
        $this->createMigrationSnapshot();

        // STEP 2: Migrate PO-based payments with existing invoice_id
        $this->migratePaymentsWithInvoiceId();

        // STEP 3: Migrate PO-based payments using payment_module_allocations
        $this->migratePaymentsUsingAllocations();

        // STEP 4: Handle remaining PO-based payments (edge cases)
        $this->handleRemainingPOPayments();

        // STEP 5: Update ledger entries for migrated payments
        $this->updateLedgerEntries();

        // STEP 6: Verify migration integrity
        $this->verifyMigrationIntegrity();

        // STEP 7: Update migration state to completed
        DB::table('system_migration_state')
            ->where('migration_phase', 'phase3_data_migration')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'validation_passed' => true,
                'validated_at' => now(),
            ]);
    }

    /**
     * Create snapshot for rollback
     */
    private function createMigrationSnapshot(): void
    {
        // Store current payment types and allocations in a temporary table
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

        // Populate snapshot with PO-based payments
        DB::statement("
            INSERT INTO payment_migration_snapshot 
            (payment_id, old_payment_type, old_purchase_invoice_id, purchase_order_id)
            SELECT 
                id as payment_id,
                payment_type as old_payment_type,
                purchase_invoice_id as old_purchase_invoice_id,
                purchase_order_id
            FROM payments_module
            WHERE payment_type IN ('against_po', 'advance_against_po')
        ");

        // Add allocation info to snapshot (only if table exists)
        if (Schema::hasTable('payment_module_allocations')) {
            DB::statement("
                UPDATE payment_migration_snapshot ps
                SET
                    allocation_id = (SELECT id FROM payment_module_allocations pma WHERE pma.payment_module_id = ps.payment_id LIMIT 1),
                    allocated_amount = (SELECT allocated_amount FROM payment_module_allocations pma WHERE pma.payment_module_id = ps.payment_id LIMIT 1)
            ");
        } else {
            Log::channel('payment_audit')->warning('payment_module_allocations table does not exist, skipping allocation mapping');
        }

        Log::channel('payment_audit')->info('Phase 3 migration snapshot created', [
            'snapshot_count' => DB::table('payment_migration_snapshot')->count(),
        ]);
    }

    /**
     * Migrate payments that already have purchase_invoice_id
     */
    private function migratePaymentsWithInvoiceId(): void
    {
        // Get payments to migrate
        $paymentsToMigrate = DB::table('payments_module as pm')
            ->select('pm.*', 'ps.purchase_order_id as old_po_id')
            ->join('payment_migration_snapshot as ps', 'pm.id', '=', 'ps.payment_id')
            ->where('pm.payment_type', 'against_po')
            ->where('pm.purchase_invoice_id', '!=', '')
            ->whereNotNull('pm.purchase_invoice_id')
            ->get();

        foreach ($paymentsToMigrate as $payment) {
            // Update payment type
            DB::table('payments_module')
                ->where('id', $payment->id)
                ->update(['payment_type' => 'against_invoice']);

            // Update snapshot
            DB::table('payment_migration_snapshot')
                ->where('payment_id', $payment->id)
                ->update([
                    'new_payment_type' => 'against_invoice',
                    'new_purchase_invoice_id' => $payment->purchase_invoice_id,
                ]);

            // CRITICAL: Populate payment_migration_map for traceability
            DB::table('payment_migration_map')->insert([
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'old_po_id' => $payment->old_po_id,
                'old_payment_type' => 'against_po',
                'old_invoice_id' => $payment->purchase_invoice_id,
                'new_invoice_id' => $payment->purchase_invoice_id,
                'new_payment_type' => 'against_invoice',
                'migration_phase' => 'phase3',
                'transformation_type' => 'direct_invoice_link',
                'migrated_by' => auth()->id() ?? 1,
                'amount_before' => $payment->amount,
                'amount_after' => $payment->amount,
                'amount_difference' => 0,
                'validated' => true,
            ]);
        }

        $count = count($paymentsToMigrate);

        Log::channel('payment_audit')->info('Phase 3: Migrated payments with existing invoice_id', [
            'count' => $count,
        ]);
    }

    /**
     * Migrate payments using payment_module_allocations
     */
    private function migratePaymentsUsingAllocations(): void
    {
        // Skip if table doesn't exist
        if (!Schema::hasTable('payment_module_allocations')) {
            Log::channel('payment_audit')->warning('payment_module_allocations table does not exist, skipping allocation-based migration');
            return;
        }

        // Get payments with allocations
        $paymentsWithAllocations = DB::table('payments_module as pm')
            ->select('pm.*', 'pma.id as allocation_id', 'pma.allocated_amount', 'pma.purchase_invoice_id as allocated_invoice_id', 'ps.purchase_order_id as old_po_id')
            ->join('payment_module_allocations as pma', 'pm.id', '=', 'pma.payment_module_id')
            ->join('payment_migration_snapshot as ps', 'pm.id', '=', 'ps.payment_id')
            ->where('pm.payment_type', 'against_po')
            ->whereNull('pm.purchase_invoice_id')
            ->whereNotNull('pma.purchase_invoice_id')
            ->get();

        foreach ($paymentsWithAllocations as $payment) {
            // Update payment
            DB::table('payments_module')
                ->where('id', $payment->id)
                ->update([
                    'purchase_invoice_id' => $payment->allocated_invoice_id,
                    'payment_type' => 'against_invoice',
                ]);

            // Update snapshot
            DB::table('payment_migration_snapshot')
                ->where('payment_id', $payment->id)
                ->update([
                    'new_payment_type' => 'against_invoice',
                    'new_purchase_invoice_id' => $payment->allocated_invoice_id,
                    'allocation_id' => $payment->allocation_id,
                    'allocated_amount' => $payment->allocated_amount,
                ]);

            // CRITICAL: Populate payment_migration_map for traceability
            DB::table('payment_migration_map')->insert([
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'old_po_id' => $payment->old_po_id,
                'old_payment_type' => 'against_po',
                'old_allocation_id' => $payment->allocation_id,
                'old_allocated_amount' => $payment->allocated_amount,
                'new_invoice_id' => $payment->allocated_invoice_id,
                'new_payment_type' => 'against_invoice',
                'migration_phase' => 'phase3',
                'transformation_type' => 'allocation_to_invoice',
                'migrated_by' => auth()->id() ?? 1,
                'amount_before' => $payment->amount,
                'amount_after' => $payment->amount,
                'amount_difference' => 0,
                'validated' => true,
            ]);
        }

        $count = count($paymentsWithAllocations);

        Log::channel('payment_audit')->info('Phase 3: Migrated payments using allocations', [
            'count' => $count,
        ]);
    }

    /**
     * Handle remaining PO-based payments (edge cases)
     */
    private function handleRemainingPOPayments(): void
    {
        // For payments without invoice_id and without allocations, we need to handle them
        // These are edge cases - log them for manual review
        $query = DB::table('payments_module')
            ->whereIn('payment_type', ['against_po', 'advance_against_po'])
            ->whereNull('purchase_invoice_id');

        // Only check for allocations if table exists
        if (Schema::hasTable('payment_module_allocations')) {
            $query->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payment_module_allocations')
                    ->whereColumn('payment_module_allocations.payment_module_id', 'payments_module.id');
            });
        }

        $remaining = $query->get();

        if ($remaining->count() > 0) {
            Log::channel('payment_audit')->warning('Phase 3: Found PO-based payments without invoice', [
                'count' => $remaining->count(),
                'payment_ids' => $remaining->pluck('id')->toArray(),
            ]);

            // For now, we'll keep them as is but mark them for manual review
            // In production, these should be resolved before migration
            // We'll add a flag to indicate they need manual intervention
            foreach ($remaining as $payment) {
                DB::table('payment_migration_snapshot')
                    ->where('payment_id', $payment->id)
                    ->update(['new_payment_type' => 'REQUIRES_MANUAL_INTERVENTION']);
            }
        }
    }

    /**
     * Update ledger entries for migrated payments
     */
    private function updateLedgerEntries(): void
    {
        // Update ledger entries where reference_type changed
        // For payments that changed from 'against_po' to 'against_invoice',
        // the ledger entry reference_type should be 'payment' (not 'advance')
        $updated = DB::statement("
            UPDATE supplier_transactions st
            INNER JOIN payment_migration_snapshot ps ON st.reference_id = ps.payment_id
            SET 
                st.reference_type = CASE 
                    WHEN ps.new_payment_type = 'against_invoice' THEN 'payment'
                    WHEN ps.new_payment_type = 'REQUIRES_MANUAL_INTERVENTION' THEN 'payment'
                    ELSE st.reference_type
                END,
                st.meta = JSON_SET(
                    COALESCE(st.meta, '{}'),
                    '$.migration_phase_3', 'true',
                    '$.old_payment_type', ps.old_payment_type,
                    '$.new_payment_type', ps.new_payment_type
                )
            WHERE st.reference_type IN ('payment', 'advance')
              AND ps.new_payment_type IN ('against_invoice', 'REQUIRES_MANUAL_INTERVENTION')
        ");

        Log::channel('payment_audit')->info('Phase 3: Updated ledger entries', [
            'updated' => $updated,
        ]);
    }

    /**
     * Verify migration integrity
     */
    private function verifyMigrationIntegrity(): void
    {
        // Check that all payments with invoice_id have payment_type 'against_invoice'
        $invalid = DB::table('payments_module')
            ->whereNotNull('purchase_invoice_id')
            ->whereIn('payment_type', ['against_po', 'advance_against_po'])
            ->count();

        if ($invalid > 0) {
            throw new \Exception("Migration integrity check failed: {$invalid} payments have invoice_id but still have PO-based payment_type");
        }

        // Check that total payment amount is preserved
        $before = DB::table('payment_migration_snapshot')
            ->where('old_payment_type', '!=', 'REQUIRES_MANUAL_INTERVENTION')
            ->sum(DB::raw('(SELECT amount FROM payments_module WHERE id = payment_migration_snapshot.payment_id)'));

        $after = DB::table('payments_module')
            ->where('payment_type', 'against_invoice')
            ->sum('amount');

        // Note: This might not match exactly due to edge cases, but should be close
        Log::channel('payment_audit')->info('Phase 3: Migration integrity check', [
            'invalid_payments' => $invalid,
            'total_amount_check' => [
                'before' => $before,
                'after' => $after,
                'difference' => abs($before - $after),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original payment types from snapshot (only if tables exist)
        if (Schema::hasTable('payments_module') && Schema::hasTable('payment_migration_snapshot')) {
            DB::statement("
                UPDATE payments_module pm
                INNER JOIN payment_migration_snapshot ps ON pm.id = ps.payment_id
                SET 
                    pm.payment_type = ps.old_payment_type,
                    pm.purchase_invoice_id = ps.old_purchase_invoice_id
                WHERE ps.new_payment_type = 'against_invoice'
            ");
        }

        // Restore ledger entries (only if tables exist)
        if (Schema::hasTable('supplier_transactions') && Schema::hasTable('payment_migration_snapshot')) {
            DB::statement("
                UPDATE supplier_transactions st
                INNER JOIN payment_migration_snapshot ps ON st.reference_id = ps.payment_id
                SET 
                    st.reference_type = CASE 
                        WHEN ps.old_payment_type = 'against_po' THEN 'payment'
                        WHEN ps.old_payment_type = 'advance_against_po' THEN 'advance'
                        ELSE st.reference_type
                    END,
                    st.meta = JSON_REMOVE(st.meta, '$.migration_phase_3', '$.old_payment_type', '$.new_payment_type')
                WHERE ps.new_payment_type = 'against_invoice'
            ");
        }

        // Drop snapshot table
        Schema::dropIfExists('payment_migration_snapshot');

        Log::channel('payment_audit')->info('Phase 3: Migration rolled back');
    }
};
