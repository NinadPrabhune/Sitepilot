<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;

class SmokeTestPoLockedSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smoke:po-locked-system';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated smoke test for PO-Locked Advance System - validates feature flag isolation';

    /**
     * Test data IDs for cleanup
     */
    private $supplierId = null;
    private $poId = null;
    private $invoiceId = null;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 PO-Locked Advance System Smoke Test');
        $this->info('========================================');
        $this->info('');
        $this->info('Feature Flag Status:');
        $this->info('PO_LOCKED_ADVANCE_ENABLED: ' . (config('finance.po_locked_advance_enabled', false) ? 'ON' : 'OFF'));
        $this->info('');

        try {
            return DB::transaction(function () {
                // Phase 1: Create Test Supplier
                $this->info('Phase 1: Creating test supplier...');
                $supplier = Supplier::create([
                    'name' => 'Smoke Test Supplier ' . time(),
                    'contact_person' => 'Test Contact',
                    'email' => 'smoke-test@example.com',
                    'phone' => '1234567890',
                    'address' => 'Test Address',
                    'status' => 'active',
                ]);
                $this->supplierId = $supplier->id;
                $this->info("✓ Supplier created: ID {$supplier->id}");
                $this->info('');

                // Phase 2: Create Test PO
                $this->info('Phase 2: Creating test PO...');
                $po = PurchaseOrder::create([
                    'supplier_id' => $supplier->id,
                    'po_number' => 'SMOKE-PO-' . time(),
                    'po_date' => now()->toDateString(),
                    'total_amount' => 10000.00,
                    'status' => 'pending',
                    'site_id' => 1,
                    'workspace_id' => 1,
                ]);
                $this->poId = $po->id;
                $this->info("✓ PO created: ID {$po->id}");
                $this->info('');

                // Phase 3: Create Test Invoice
                $this->info('Phase 3: Creating test invoice linked to PO...');
                $invoice = PurchaseInvoice::create([
                    'supplier_id' => $supplier->id,
                    'po_id' => $po->id,
                    'invoice_number' => 'SMOKE-INV-' . time(),
                    'invoice_date' => now()->toDateString(),
                    'grand_total' => 10000.00,
                    'total_taxable_value' => 10000.00,
                    'total_tax' => 0,
                    'total_cgst' => 0,
                    'total_sgst' => 0,
                    'total_igst' => 0,
                    'status' => 'Pending',
                    'payment_status' => 'unpaid',
                    'site_id' => 1,
                    'workspace_id' => 1,
                    'created_by' => 1,
                ]);
                $this->invoiceId = $invoice->id;
                $this->info("✓ Invoice created: ID {$invoice->id}");
                $this->info('');

                // Phase 4: Validate Feature Flag Isolation
                $this->info('Phase 4: Validating feature flag isolation...');
                $result = DB::table('purchase_invoices')
                    ->where('id', $invoice->id)
                    ->first();

                $this->table(['Field', 'Value'], [
                    ['ID', $result->id],
                    ['Invoice Number', $result->invoice_number],
                    ['PO ID', $result->po_id],
                    ['Transaction Flow ID', $result->transaction_flow_id ?? 'NULL'],
                    ['GRN Type', $result->grn_type ?? 'NULL'],
                ]);

                // Validation checks
                $errors = [];

                if ($result->transaction_flow_id !== null) {
                    $errors[] = '❌ FAIL: transaction_flow_id is NOT NULL (feature flag leakage)';
                } else {
                    $this->info('✓ PASS: transaction_flow_id is NULL (correct)');
                }

                if ($result->grn_type !== null) {
                    $errors[] = '❌ FAIL: grn_type is NOT NULL (feature flag leakage)';
                } else {
                    $this->info('✓ PASS: grn_type is NULL (correct)');
                }

                // Phase 5: Check for side effects
                $this->info('');
                $this->info('Phase 5: Checking for side effects...');

                $utilizationCount = DB::table('advance_utilizations')
                    ->where('purchase_invoice_id', $invoice->id)
                    ->count();

                if ($utilizationCount > 0) {
                    $errors[] = "❌ FAIL: advance_utilizations has {$utilizationCount} records (should be 0)";
                } else {
                    $this->info('✓ PASS: No advance utilization records (correct)');
                }

                // Phase 6: Cleanup test data
                $this->info('');
                $this->info('Phase 6: Cleaning up test data...');
                $this->cleanup();
                $this->info('✓ Test data cleaned up');
                $this->info('');

                // Final verdict
                if (empty($errors)) {
                    $this->info('========================================');
                    $this->info('🟢 SMOKE TEST: PASSED');
                    $this->info('========================================');
                    $this->info('');
                    $this->info('✓ Feature flag isolation: WORKING');
                    $this->info('✓ No side effects detected');
                    $this->info('✓ System is safe for further testing');
                    return 0;
                } else {
                    $this->info('========================================');
                    $this->info('🔴 SMOKE TEST: FAILED');
                    $this->info('========================================');
                    $this->info('');
                    foreach ($errors as $error) {
                        $this->error($error);
                    }
                    $this->info('');
                    $this->error('⚠️ CRITICAL: Feature flag leakage detected!');
                    $this->error('⚠️ System is NOT safe for production deployment');
                    return 1;
                }
            });
        } catch (\Exception $e) {
            $this->error('❌ SMOKE TEST: ERROR');
            $this->error('Error: ' . $e->getMessage());
            $this->cleanup();
            return 1;
        }
    }

    /**
     * Clean up test data
     */
    private function cleanup()
    {
        if ($this->invoiceId) {
            DB::table('purchase_invoices')->where('id', $this->invoiceId)->delete();
        }
        if ($this->poId) {
            DB::table('purchase_orders')->where('id', $this->poId)->delete();
        }
        if ($this->supplierId) {
            DB::table('suppliers')->where('id', $this->supplierId)->delete();
        }
    }
}
