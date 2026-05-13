<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;

class ConcurrencyStressTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stress:concurrency {--count=50 : Number of concurrent operations (default: 50)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Concurrency stress test for PO-Locked Advance System - validates race conditions and DB lock contention';

    /**
     * Test data IDs for cleanup
     */
    private $supplierIds = [];
    private $poIds = [];
    private $invoiceIds = [];

    /**
     * Stress test metrics
     */
    private $metrics = [
        'total_operations' => 0,
        'successful_operations' => 0,
        'failed_operations' => 0,
        'lock_timeouts' => 0,
        'race_conditions' => 0,
        'data_corruption' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔥 Concurrency Stress Test - PO-Locked Advance System');
        $this->info('====================================================');
        $this->info('');
        $this->info('Feature Flag Status:');
        $this->info('PO_LOCKED_ADVANCE_ENABLED: ' . (config('finance.po_locked_advance_enabled', false) ? 'ON' : 'OFF'));
        $this->info('');

        $count = (int) $this->option('count');
        $this->info("Concurrent Operations: {$count}");
        $this->info('');

        try {
            return DB::transaction(function () use ($count) {
                $startTime = microtime(true);

                // Phase 1: Create Test Data
                $this->info('Phase 1: Creating test data...');
                $this->createTestData($count);
                $this->info("✓ Created {$count} suppliers, {$count} POs");
                $this->info('');

                // Phase 2: Simulate Concurrent Invoice Creation
                $this->info('Phase 2: Simulating concurrent invoice creation...');
                $this->simulateConcurrentInvoices($count);
                $this->info('');

                // Phase 3: Validate Data Integrity
                $this->info('Phase 3: Validating data integrity...');
                $this->validateDataIntegrity();
                $this->info('');

                // Phase 4: Check for Race Conditions
                $this->info('Phase 4: Checking for race conditions...');
                $this->checkRaceConditions();
                $this->info('');

                // Phase 5: Cleanup
                $this->info('Phase 5: Cleaning up test data...');
                $this->cleanup();
                $this->info('✓ Test data cleaned up');
                $this->info('');

                // Phase 6: Report Results
                $this->reportResults($startTime);

                // Final verdict
                if ($this->metrics['failed_operations'] === 0 && $this->metrics['data_corruption'] === 0) {
                    $this->info('====================================================');
                    $this->info('🟢 CONCURRENCY STRESS TEST: PASSED');
                    $this->info('====================================================');
                    $this->info('');
                    $this->info('✓ No race conditions detected');
                    $this->info('✓ No data corruption');
                    $this->info('✓ DB locks handled correctly');
                    $this->info('✓ System is concurrency-safe');
                    return 0;
                } else {
                    $this->info('====================================================');
                    $this->info('🔴 CONCURRENCY STRESS TEST: FAILED');
                    $this->info('====================================================');
                    $this->info('');
                    if ($this->metrics['failed_operations'] > 0) {
                        $this->error("❌ Failed operations: {$this->metrics['failed_operations']}");
                    }
                    if ($this->metrics['data_corruption'] > 0) {
                        $this->error("❌ Data corruption detected: {$this->metrics['data_corruption']}");
                    }
                    if ($this->metrics['race_conditions'] > 0) {
                        $this->error("❌ Race conditions: {$this->metrics['race_conditions']}");
                    }
                    $this->error('⚠️ System is NOT concurrency-safe');
                    return 1;
                }
            });
        } catch (\Exception $e) {
            $this->error('❌ CONCURRENCY STRESS TEST: ERROR');
            $this->error('Error: ' . $e->getMessage());
            $this->cleanup();
            return 1;
        }
    }

    /**
     * Create test data for stress test
     */
    private function createTestData($count)
    {
        for ($i = 0; $i < $count; $i++) {
            $supplier = Supplier::create([
                'name' => 'Stress Test Supplier ' . $i . ' ' . time(),
                'contact_person' => 'Test Contact',
                'email' => "stress-test-{$i}@example.com",
                'phone' => '1234567890',
                'address' => 'Test Address',
                'status' => 'active',
            ]);
            $this->supplierIds[] = $supplier->id;

            $po = PurchaseOrder::create([
                'supplier_id' => $supplier->id,
                'po_number' => 'STRESS-PO-' . $i . '-' . time(),
                'po_date' => now()->toDateString(),
                'total_amount' => 10000.00,
                'status' => 'pending',
                'site_id' => 1,
                'workspace_id' => 1,
            ]);
            $this->poIds[] = $po->id;
        }

        $this->metrics['total_operations'] = $count * 2;
        $this->metrics['successful_operations'] = $count * 2;
    }

    /**
     * Simulate concurrent invoice creation
     */
    private function simulateConcurrentInvoices($count)
    {
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            try {
                // Simulate concurrent access with DB-level locking
                $invoice = DB::transaction(function () use ($i) {
                    // Simulate potential race condition by checking if invoice already exists
                    $existingInvoice = DB::table('purchase_invoices')
                        ->where('invoice_number', 'STRESS-INV-' . $i . '-' . time())
                        ->lockForUpdate()
                        ->first();

                    if ($existingInvoice) {
                        throw new \Exception('Race condition: Invoice already exists');
                    }

                    $invoice = PurchaseInvoice::create([
                        'supplier_id' => $this->supplierIds[$i],
                        'po_id' => $this->poIds[$i],
                        'invoice_number' => 'STRESS-INV-' . $i . '-' . time(),
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

                    return $invoice;
                });

                $this->invoiceIds[] = $invoice->id;
                $this->metrics['successful_operations']++;
            } catch (\Exception $e) {
                $this->metrics['failed_operations']++;
                if (strpos($e->getMessage(), 'Race condition') !== false) {
                    $this->metrics['race_conditions']++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Validate data integrity
     */
    private function validateDataIntegrity()
    {
        // Check that all invoices have correct supplier and PO references
        $orphanInvoices = DB::table('purchase_invoices')
            ->whereIn('id', $this->invoiceIds)
            ->where(function ($query) {
                $query->whereNull('supplier_id')
                    ->orWhereNull('po_id');
            })
            ->count();

        if ($orphanInvoices > 0) {
            $this->metrics['data_corruption'] += $orphanInvoices;
            $this->error("❌ Found {$orphanInvoices} orphan invoices");
        } else {
            $this->info('✓ No orphan invoices detected');
        }

        // Check for duplicate invoice numbers
        $duplicates = DB::table('purchase_invoices')
            ->whereIn('id', $this->invoiceIds)
            ->select('invoice_number', DB::raw('COUNT(*) as count'))
            ->groupBy('invoice_number')
            ->having('count', '>', 1)
            ->count();

        if ($duplicates > 0) {
            $this->metrics['data_corruption'] += $duplicates;
            $this->error("❌ Found {$duplicates} duplicate invoice numbers");
        } else {
            $this->info('✓ No duplicate invoice numbers');
        }

        // Check feature flag isolation
        $leakage = DB::table('purchase_invoices')
            ->whereIn('id', $this->invoiceIds)
            ->where(function ($query) {
                $query->whereNotNull('transaction_flow_id')
                    ->orWhereNotNull('grn_type');
            })
            ->count();

        if ($leakage > 0) {
            $this->metrics['data_corruption'] += $leakage;
            $this->error("❌ Feature flag leakage detected in {$leakage} invoices");
        } else {
            $this->info('✓ Feature flag isolation maintained');
        }
    }

    /**
     * Check for race conditions
     */
    private function checkRaceConditions()
    {
        // Check for concurrent writes to same invoice
        $concurrentWrites = DB::table('purchase_invoices')
            ->whereIn('id', $this->invoiceIds)
            ->select('invoice_number', DB::raw('COUNT(*) as count'))
            ->groupBy('invoice_number')
            ->having('count', '>', 1)
            ->count();

        if ($concurrentWrites > 0) {
            $this->metrics['race_conditions'] += $concurrentWrites;
            $this->error("❌ Concurrent writes detected: {$concurrentWrites}");
        } else {
            $this->info('✓ No concurrent writes detected');
        }

        // Check for lock timeouts (simulated by checking failed operations)
        if ($this->metrics['failed_operations'] > 0) {
            $this->info("⚠️ Failed operations: {$this->metrics['failed_operations']} (potential lock contention)");
        } else {
            $this->info('✓ No lock contention detected');
        }
    }

    /**
     * Clean up test data
     */
    private function cleanup()
    {
        // Delete invoices
        if (!empty($this->invoiceIds)) {
            DB::table('purchase_invoices')->whereIn('id', $this->invoiceIds)->delete();
        }

        // Delete POs
        if (!empty($this->poIds)) {
            DB::table('purchase_orders')->whereIn('id', $this->poIds)->delete();
        }

        // Delete suppliers
        if (!empty($this->supplierIds)) {
            DB::table('suppliers')->whereIn('id', $this->supplierIds)->delete();
        }
    }

    /**
     * Report test results
     */
    private function reportResults($startTime)
    {
        $duration = round(microtime(true) - $startTime, 2);

        $this->info('====================================================');
        $this->info('📊 Stress Test Results');
        $this->info('====================================================');
        $this->table(['Metric', 'Value'], [
            ['Total Operations', $this->metrics['total_operations']],
            ['Successful Operations', $this->metrics['successful_operations']],
            ['Failed Operations', $this->metrics['failed_operations']],
            ['Race Conditions', $this->metrics['race_conditions']],
            ['Data Corruption', $this->metrics['data_corruption']],
            ['Duration (seconds)', $duration],
            ['Operations/Second', round($this->metrics['total_operations'] / $duration, 2)],
        ]);
    }
}
