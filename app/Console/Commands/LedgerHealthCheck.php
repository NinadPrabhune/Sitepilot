<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SupplierTransaction;

class LedgerHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:health-check {--fail-on-warning : Exit with error code on warnings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-deployment health check for ledger integrity';

    /**
     * Exit codes
     */
    const EXIT_SUCCESS = 0;
    const EXIT_WARNING = 1;
    const EXIT_ERROR = 2;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== LEDGER HEALTH CHECK ===');
        $this->newLine();

        $issues = [];
        $warnings = [];

        // Check 1: Duplicate ledger entries
        $this->info('Check 1: Duplicate ledger entries...');
        $duplicates = DB::select("
            SELECT reference_type, reference_id, supplier_id, site_id, COUNT(*) as count, GROUP_CONCAT(id) as ids
            FROM supplier_transactions
            GROUP BY reference_type, reference_id, supplier_id, site_id
            HAVING count > 1
        ");

        if (!empty($duplicates)) {
            $count = count($duplicates);
            $this->error("✗ FAIL: Found {$count} duplicate groups");
            foreach ($duplicates as $dup) {
                $this->line("  - Type: {$dup->reference_type}, RefID: {$dup->reference_id}, Supplier: {$dup->supplier_id}, Site: {$dup->site_id}, Count: {$dup->count}");
            }
            $issues[] = "Duplicate ledger entries found";
        } else {
            $this->info('✓ PASS: No duplicate entries');
        }

        // Check 2: Negative balances (warning only - credit balances are valid)
        $this->info('Check 2: Negative balances...');
        $negativeBalances = SupplierTransaction::where('balance', '<', 0)->count();

        if ($negativeBalances > 0) {
            $this->warn("⚠ WARNING: Found {$negativeBalances} transactions with negative balance (credit balances may be valid)");
            $warnings[] = "Negative balances found";
        } else {
            $this->info('✓ PASS: No negative balances');
        }

        // Check 3: Orphan ledger entries
        $this->info('Check 3: Orphan ledger entries...');
        $orphans = DB::select("
            SELECT st.*
            FROM supplier_transactions st
            LEFT JOIN purchase_orders po ON st.reference_type = 'po' AND st.reference_id = po.id
            LEFT JOIN purchase_invoices pi ON st.reference_type = 'invoice' AND st.reference_id = pi.id
            LEFT JOIN payments_module pm ON st.reference_type IN ('payment', 'advance') AND st.reference_id = pm.id
            WHERE st.reference_type IN ('po', 'invoice', 'payment', 'advance')
              AND (
                (st.reference_type = 'po' AND po.id IS NULL)
                OR (st.reference_type = 'invoice' AND pi.id IS NULL)
                OR (st.reference_type IN ('payment', 'advance') AND pm.id IS NULL)
              )
        ");

        if (!empty($orphans)) {
            $count = count($orphans);
            $this->error("✗ FAIL: Found {$count} orphan ledger entries");
            $issues[] = "Orphan ledger entries found";
        } else {
            $this->info('✓ PASS: No orphan entries');
        }

        // Check 4: Balance calculation consistency (simplified - check for null balances only)
        $this->info('Check 4: Balance calculation consistency...');
        $nullBalances = SupplierTransaction::whereNull('balance')->count();

        if ($nullBalances > 0) {
            $this->error("✗ FAIL: Found {$nullBalances} transactions with null balance");
            $issues[] = "Null balance fields found";
        } else {
            $this->info('✓ PASS: All balances have values');
        }

        // Check 5: Payment-to-ledger reconciliation
        $this->info('Check 5: Payment-to-ledger reconciliation...');
        $paymentMismatches = DB::select("
            SELECT 
                pm.id as payment_id,
                pm.payment_number,
                pm.amount as payment_amount,
                COUNT(st.id) as ledger_count,
                SUM(st.credit) as total_credit
            FROM payments_module pm
            LEFT JOIN supplier_transactions st ON st.reference_type IN ('payment', 'advance') AND st.reference_id = pm.id
            GROUP BY pm.id, pm.payment_number, pm.amount
            HAVING ledger_count != 1 OR total_credit != pm.amount
        ");

        if (!empty($paymentMismatches)) {
            $count = count($paymentMismatches);
            $this->error("✗ FAIL: Found {$count} payment-to-ledger mismatches");
            $issues[] = "Payment-to-ledger mismatches found";
        } else {
            $this->info('✓ PASS: Payment-to-ledger reconciliation successful');
        }

        // Check 6: Future transaction dates (warning)
        $this->info('Check 6: Future transaction dates...');
        $futureDates = SupplierTransaction::where('transaction_date', '>', now())->count();

        if ($futureDates > 0) {
            $this->warn("⚠ WARNING: Found {$futureDates} transactions with future dates");
            $warnings[] = "Future transaction dates found";
        } else {
            $this->info('✓ PASS: No future transaction dates');
        }

        // Check 7: Reversal entries without original payment (warning)
        $this->info('Check 7: Reversal entries without original payment...');
        $orphanReversals = DB::select("
            SELECT st.*
            FROM supplier_transactions st
            LEFT JOIN payments_module pm ON st.reference_id = pm.id
            WHERE st.reference_type = 'adjustment'
              AND JSON_EXTRACT(st.meta, '$.reversal_of') IS NOT NULL
              AND pm.id IS NULL
        ");

        if (!empty($orphanReversals)) {
            $count = count($orphanReversals);
            $this->warn("⚠ WARNING: Found {$count} reversal entries without original payment");
            $warnings[] = "Orphan reversal entries found";
        } else {
            $this->info('✓ PASS: All reversal entries have original payment');
        }

        // Summary
        $this->newLine();
        $this->info('=== SUMMARY ===');
        $this->line("Errors: " . count($issues));
        $this->line("Warnings: " . count($warnings));

        if (!empty($issues)) {
            $this->newLine();
            $this->error('HEALTH CHECK FAILED');
            $this->line('Issues found:');
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
            $this->newLine();
            $this->line('Run php artisan ledger:rebuild to fix balance issues.');
            return self::EXIT_ERROR;
        }

        if (!empty($warnings)) {
            $this->newLine();
            $this->warn('HEALTH CHECK PASSED WITH WARNINGS');
            $this->line('Warnings found:');
            foreach ($warnings as $warning) {
                $this->line("  - {$warning}");
            }
            $this->newLine();
            
            if ($this->option('fail-on-warning')) {
                return self::EXIT_WARNING;
            }
            return self::EXIT_SUCCESS;
        }

        $this->newLine();
        $this->info('✓ HEALTH CHECK PASSED');
        $this->line('All ledger integrity checks passed.');
        return self::EXIT_SUCCESS;
    }
}
