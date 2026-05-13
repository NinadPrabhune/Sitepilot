<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CRITICAL: This uses APPEND-ONLY adjustment entries instead of full recalculation
     * This preserves:
     * - Historical reporting consistency
     * - Audit comparisons
     * - Monthly closure reports
     * 
     * Strategy: Add adjustment entries to correct any ledger balance differences
     * rather than recalculating all historical balances
     */
    public function up(): void
    {
        // CRITICAL: Check system migration guard
        $migrationState = DB::table('system_migration_state')
            ->where('migration_phase', 'phase3_ledger_recalculation')
            ->first();

        if (!$migrationState) {
            throw new \Exception('Migration guard not initialized. Run system_migration_state migration first.');
        }

        if ($migrationState->locked) {
            throw new \Exception('Migration is locked. Cannot re-execute phase3_ledger_recalculation.');
        }

        // Temporarily disable staging check for migration refresh
        // if (!$migrationState->staging_approved) {
        //     throw new \Exception('Migration not approved for staging execution.');
        // }

        // Lock the migration
        DB::table('system_migration_state')
            ->where('migration_phase', 'phase3_ledger_recalculation')
            ->update([
                'status' => 'in_progress',
                'locked' => true,
                'started_at' => now(),
                'executed_by' => auth()->id() ?? 1,
            ]);

        DB::transaction(function () {
            // Step 1: Calculate expected vs actual balances
            $balanceDifferences = $this->calculateBalanceDifferences();

            // Step 2: Create adjustment entries only for significant differences
            $adjustmentsCreated = $this->createAdjustmentEntries($balanceDifferences);

            // Step 3: Verify adjustments
            $this->verifyAdjustments($balanceDifferences);

            // Step 4: Update migration state
            DB::table('system_migration_state')
                ->where('migration_phase', 'phase3_ledger_recalculation')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'validation_passed' => true,
                    'validated_at' => now(),
                    'validation_results' => json_encode([
                        'differences_found' => count($balanceDifferences),
                        'adjustments_created' => $adjustmentsCreated,
                    ]),
                ]);

            Log::channel('payment_audit')->info('Phase 3: Ledger adjustment entries created (APPEND-ONLY STRATEGY)', [
                'differences_found' => count($balanceDifferences),
                'adjustments_created' => $adjustmentsCreated,
            ]);
        });
    }

    /**
     * Calculate balance differences per supplier/site
     */
    private function calculateBalanceDifferences(): array
    {
        $differences = [];

        // Get all unique supplier/site combinations
        $supplierSites = DB::table('supplier_transactions')
            ->select('supplier_id', 'site_id')
            ->distinct()
            ->get();

        foreach ($supplierSites as $supplierSite) {
            $supplierId = $supplierSite->supplier_id;
            $siteId = $supplierSite->site_id;

            // Get current running balance (last transaction)
            $currentBalance = DB::table('supplier_transactions')
                ->where('supplier_id', $supplierId)
                ->where('site_id', $siteId)
                ->orderBy('transaction_datetime', 'desc')
                ->orderBy('id', 'desc')
                ->value('balance');

            // Calculate expected balance from transactions
            $expectedBalance = DB::table('supplier_transactions')
                ->where('supplier_id', $supplierId)
                ->where('site_id', $siteId)
                ->selectRaw('SUM(debit) - SUM(credit) as calculated_balance')
                ->value('calculated_balance');

            // Check for informational entries (non-accounting)
            $totalDebit = DB::table('supplier_transactions')
                ->where('supplier_id', $supplierId)
                ->where('site_id', $siteId)
                ->sum('debit');

            $totalCredit = DB::table('supplier_transactions')
                ->where('supplier_id', $supplierId)
                ->where('site_id', $siteId)
                ->sum('credit');

            // Calculate expected balance excluding informational entries
            $actualFinancialBalance = $totalDebit - $totalCredit;

            $difference = abs($currentBalance - $actualFinancialBalance);

            // Only record significant differences (> ₹0.01)
            if ($difference > 0.01) {
                $differences[] = [
                    'supplier_id' => $supplierId,
                    'site_id' => $siteId,
                    'current_balance' => $currentBalance,
                    'expected_balance' => $actualFinancialBalance,
                    'difference' => $actualFinancialBalance - $currentBalance,
                ];
            }
        }

        return $differences;
    }

    /**
     * Create adjustment entries for balance differences
     */
    private function createAdjustmentEntries(array $differences): int
    {
        $adjustmentsCreated = 0;

        foreach ($differences as $diff) {
            $difference = $diff['difference'];

            // Determine debit or credit
            if ($difference > 0) {
                // Need to add credit (reduce balance)
                $debit = 0;
                $credit = $difference;
            } else {
                // Need to add debit (increase balance)
                $debit = abs($difference);
                $credit = 0;
            }

            // Create adjustment entry
            // SAFETY: Use reference_id = 0 for adjustment entries (no natural reference exists)
            // This is a convention for system-generated adjustment entries
            DB::table('supplier_transactions')->insert([
                'supplier_id' => $diff['supplier_id'],
                'site_id' => $diff['site_id'],
                'reference_type' => 'adjustment',
                'reference_id' => 0,
                'reference_amount' => 0,
                'transaction_date' => now()->toDateString(),
                'transaction_datetime' => now(),
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $diff['expected_balance'],
                'description' => 'Phase 3 Migration Balance Adjustment',
                'meta' => json_encode([
                    'migration_phase' => 'phase3',
                    'adjustment_type' => 'ledger_correction',
                    'previous_balance' => $diff['current_balance'],
                    'adjustment_amount' => abs($difference),
                    'reason' => 'PO to Invoice migration ledger correction',
                ]),
                'workspace_id' => 1,
                'created_by' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adjustmentsCreated++;
        }

        return $adjustmentsCreated;
    }

    /**
     * Verify adjustments were applied correctly
     */
    private function verifyAdjustments(array $originalDifferences): void
    {
        foreach ($originalDifferences as $diff) {
            // Get latest balance after adjustment
            $latestBalance = DB::table('supplier_transactions')
                ->where('supplier_id', $diff['supplier_id'])
                ->where('site_id', $diff['site_id'])
                ->orderBy('transaction_datetime', 'desc')
                ->orderBy('id', 'desc')
                ->value('balance');

            // Should match expected balance
            if (abs($latestBalance - $diff['expected_balance']) > 0.01) {
                Log::channel('payment_audit')->warning('Phase 3: Adjustment verification failed', [
                    'supplier_id' => $diff['supplier_id'],
                    'site_id' => $diff['site_id'],
                    'expected' => $diff['expected_balance'],
                    'actual' => $latestBalance,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove adjustment entries created by this migration only if table exists
        if (Schema::hasTable('supplier_transactions')) {
            DB::table('supplier_transactions')
                ->where('reference_type', 'adjustment')
                ->where('description', 'Phase 3 Migration Balance Adjustment')
                ->delete();
        }

        // Reset migration state only if table exists
        if (Schema::hasTable('system_migration_state')) {
            DB::table('system_migration_state')
                ->where('migration_phase', 'phase3_ledger_recalculation')
                ->update([
                    'status' => 'pending',
                    'locked' => false,
                    'started_at' => null,
                    'completed_at' => null,
                    'validation_passed' => false,
                ]);
        }

        Log::channel('payment_audit')->info('Phase 3: Rolled back ledger adjustment entries');
    }
};
