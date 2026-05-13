<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\SupplierTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;
use App\Services\LedgerService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

class LedgerIntegrityTest extends TestCase
{
    /**
     * Test that no duplicate ledger entries exist
     */
    public function test_no_duplicate_ledger_entries()
    {
        $duplicates = DB::select("
            SELECT reference_type, reference_id, supplier_id, site_id, COUNT(*) as count
            FROM supplier_transactions
            GROUP BY reference_type, reference_id, supplier_id, site_id
            HAVING count > 1
        ");

        $this->assertEmpty($duplicates, 'Found duplicate ledger entries: ' . json_encode($duplicates));
    }

    /**
     * Test that balance calculation is consistent
     */
    public function test_balance_calculation_consistency()
    {
        // Get all transactions for a supplier
        $supplierId = 1;
        $transactions = SupplierTransaction::where('supplier_id', $supplierId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $runningBalance = 0;
        $ignoredTypes = [SupplierTransaction::TYPE_PO, SupplierTransaction::TYPE_GRN];

        foreach ($transactions as $tx) {
            $meta = is_array($tx->meta) ? $tx->meta : json_decode($tx->meta ?? '{}', true);
            $isNonAccounting = !empty($meta['non_accounting']);
            $isIgnoredType = in_array($tx->reference_type, $ignoredTypes);

            if (!$isIgnoredType && !$isNonAccounting) {
                $runningBalance = $runningBalance + $tx->debit - $tx->credit;
            }

            // Verify stored balance matches calculated
            $this->assertEquals(
                $runningBalance,
                $tx->balance,
                "Balance mismatch for transaction ID {$tx->id}: expected {$runningBalance}, got {$tx->balance}"
            );
        }
    }

    /**
     * Test that payment ledger entries are only created via PaymentService
     */
    public function test_payment_enforcement_guard()
    {
        $this->markTestIncomplete('Requires mocking to test enforcement guard');
    }

    /**
     * Test that ledger entries are created inside transactions
     */
    public function test_ledger_entry_transaction_safety()
    {
        DB::beginTransaction();
        try {
            $invoice = PurchaseInvoice::create([
                'supplier_id' => 1,
                'site_id' => 1,
                'grand_total' => 100000,
                'invoice_date' => now(),
                'invoice_number' => 'TEST-' . time(),
                'created_by' => 1,
                'workspace_id' => 1,
                'payment_status' => 'unpaid',
            ]);

            $ledgerService = app(LedgerService::class);
            $ledgerEntry = $ledgerService->createInvoiceEntry($invoice);

            $this->assertDatabaseHas('supplier_transactions', [
                'id' => $ledgerEntry->id,
                'reference_id' => $invoice->id,
            ]);

            // Rollback to clean up
            DB::rollBack();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->fail('Transaction safety test failed: ' . $e->getMessage());
        }

        // Verify rollback worked
        $this->assertDatabaseMissing('supplier_transactions', [
            'reference_id' => $invoice->id ?? 0,
        ]);
    }

    /**
     * Test that reversal creates correct entry
     */
    public function test_payment_reversal_creates_adjustment_entry()
    {
        $this->markTestIncomplete('Requires test payment data');
    }

    /**
     * Test that unique constraint prevents duplicates
     */
    public function test_unique_constraint_prevents_duplicates()
    {
        $this->markTestIncomplete('Requires database constraint verification');
    }

    /**
     * Test that idempotency prevents duplicate PO creation
     */
    public function test_idempotency_prevents_duplicate_po()
    {
        $this->markTestIncomplete('Requires API testing');
    }

    /**
     * Test that batch update logic is used
     */
    public function test_batch_update_logic_exists()
    {
        $ledgerHelperFile = file_get_contents(app_path('Helpers/LedgerHelper.php'));
        $this->assertStringContainsString('CASE id', $ledgerHelperFile, 'Batch update logic missing');
        $this->assertStringContainsString('WHEN', $ledgerHelperFile, 'Batch update logic missing');
    }

    /**
     * Test that deadlock retry logic exists
     */
    public function test_deadlock_retry_logic_exists()
    {
        $ledgerServiceFile = file_get_contents(app_path('Services/LedgerService.php'));
        $this->assertStringContainsString('isDeadlock', $ledgerServiceFile, 'Deadlock detection missing');
        $this->assertStringContainsString('retryWithBackoff', $ledgerServiceFile, 'Retry logic missing');
    }

    /**
     * Test that correlation ID logging exists
     */
    public function test_correlation_id_logging_exists()
    {
        $ledgerServiceFile = file_get_contents(app_path('Services/LedgerService.php'));
        $this->assertStringContainsString('getTraceId', $ledgerServiceFile, 'Correlation ID logic missing');
        $this->assertStringContainsString('X-Request-ID', $ledgerServiceFile, 'Correlation ID header missing');
    }

    /**
     * Test double spend race condition (business logic)
     * Verifies that total payments cannot exceed invoice amount
     */
    public function test_double_spend_race_condition()
    {
        $this->markTestIncomplete('Requires test invoice and payment service validation logic');
        
        // This test should verify:
        // 1. Create invoice with amount ₹1,00,000
        // 2. Attempt payment of ₹70,000 (should succeed)
        // 3. Attempt payment of ₹50,000 (should fail - exceeds remaining ₹30,000)
        // 4. Verify only ₹70,000 was paid, not ₹1,20,000
    }
}
