<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerDriftDetector extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:ledger-drift 
                            {--supplier= : Filter by supplier ID}
                            {--site= : Filter by site ID}
                            {--tolerance=0.01 : Tolerance threshold for drift detection}
                            {--output=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect ledger drift: ledger vs invoice mismatch, adjustment accumulation, supplier balance inconsistencies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $supplierId = $this->option('supplier');
        $siteId = $this->option('site');
        $tolerance = (float) $this->option('tolerance') ?? 0.01;
        $outputFormat = $this->option('output') ?? 'table';

        $this->info('Running Ledger Drift Detector...');
        $this->info("Tolerance threshold: ₹{$tolerance}");

        $drifts = [];

        // Check 1: Ledger vs Invoice mismatch
        $ledgerInvoiceMismatch = $this->checkLedgerInvoiceMismatch($supplierId, $siteId, $tolerance);
        if ($ledgerInvoiceMismatch->count() > 0) {
            $drifts['ledger_invoice_mismatch'] = $ledgerInvoiceMismatch;
        }

        // Check 2: Adjustment accumulation issues
        $adjustmentAccumulation = $this->checkAdjustmentAccumulation($supplierId, $siteId);
        if ($adjustmentAccumulation->count() > 0) {
            $drifts['adjustment_accumulation'] = $adjustmentAccumulation;
        }

        // Check 3: Supplier balance inconsistencies
        $supplierBalanceInconsistency = $this->checkSupplierBalanceInconsistency($supplierId, $siteId, $tolerance);
        if ($supplierBalanceInconsistency->count() > 0) {
            $drifts['supplier_balance_inconsistency'] = $supplierBalanceInconsistency;
        }

        // Check 4: Payment migration traceability gaps
        $migrationTraceabilityGaps = $this->checkMigrationTraceabilityGaps();
        if ($migrationTraceabilityGaps->count() > 0) {
            $drifts['migration_traceability_gaps'] = $migrationTraceabilityGaps;
        }

        $this->outputResults($drifts, $outputFormat);

        $this->logSummary($drifts);

        return empty($drifts) ? 0 : 1;
    }

    /**
     * Check 1: Ledger vs Invoice mismatch
     */
    private function checkLedgerInvoiceMismatch($supplierId = null, $siteId = null, $tolerance = 0.01)
    {
        $query = DB::table('supplier_transactions as st')
            ->select([
                'st.supplier_id',
                'st.site_id',
                DB::raw('MAX(st.balance) as ledger_balance'),
                DB::raw('COALESCE(SUM(CASE WHEN st.reference_type = "payment" THEN st.credit ELSE 0 END), 0) as total_payments'),
                DB::raw('COALESCE(SUM(CASE WHEN st.reference_type = "invoice" THEN st.debit ELSE 0 END), 0) as total_invoices'),
            ])
            ->where('st.reference_type', 'in', ['payment', 'invoice'])
            ->groupBy('st.supplier_id', 'st.site_id');

        if ($supplierId) {
            $query->where('st.supplier_id', $supplierId);
        }

        if ($siteId) {
            $query->where('st.site_id', $siteId);
        }

        $results = $query->get();

        return $results->filter(function ($item) use ($tolerance) {
            $ledgerBalance = (float) $item->ledger_balance;
            $expectedBalance = (float) $item->total_invoices - (float) $item->total_payments;
            $diff = abs($ledgerBalance - $expectedBalance);
            return $diff > $tolerance;
        })->map(function ($item) {
            $ledgerBalance = (float) $item->ledger_balance;
            $expectedBalance = (float) $item->total_invoices - (float) $item->total_payments;
            return [
                'supplier_id' => $item->supplier_id,
                'site_id' => $item->site_id,
                'ledger_balance' => $ledgerBalance,
                'expected_balance' => $expectedBalance,
                'drift_amount' => round($ledgerBalance - $expectedBalance, 2),
                'total_invoices' => $item->total_invoices,
                'total_payments' => $item->total_payments,
            ];
        });
    }

    /**
     * Check 2: Adjustment accumulation issues
     */
    private function checkAdjustmentAccumulation($supplierId = null, $siteId = null)
    {
        $query = DB::table('supplier_transactions as st')
            ->select([
                'st.supplier_id',
                'st.site_id',
                DB::raw('COUNT(CASE WHEN st.reference_type = "adjustment" THEN 1 END) as adjustment_count'),
                DB::raw('SUM(CASE WHEN st.reference_type = "adjustment" THEN ABS(st.debit - st.credit) ELSE 0 END) as total_adjustment_amount'),
                DB::raw('MAX(st.created_at) as last_adjustment_date'),
            ])
            ->where('st.reference_type', 'adjustment')
            ->groupBy('st.supplier_id', 'st.site_id');

        if ($supplierId) {
            $query->where('st.supplier_id', $supplierId);
        }

        if ($siteId) {
            $query->where('st.site_id', $siteId);
        }

        $results = $query->get();

        return $results->filter(function ($item) {
            // Flag if more than 5 adjustments or total adjustment amount > 10% of typical transaction
            return $item->adjustment_count > 5 || $item->total_adjustment_amount > 10000;
        })->map(function ($item) {
            return [
                'supplier_id' => $item->supplier_id,
                'site_id' => $item->site_id,
                'adjustment_count' => $item->adjustment_count,
                'total_adjustment_amount' => $item->total_adjustment_amount,
                'last_adjustment_date' => $item->last_adjustment_date,
                'issue' => $item->adjustment_count > 5 ? 'Too many adjustments' : 'High adjustment amount',
            ];
        });
    }

    /**
     * Check 3: Supplier balance inconsistencies
     */
    private function checkSupplierBalanceInconsistency($supplierId = null, $siteId = null, $tolerance = 0.01)
    {
        $query = DB::table('supplier_transactions as st1')
            ->select([
                'st1.supplier_id',
                'st1.site_id',
                DB::raw('MAX(CASE WHEN st1.id = latest.max_id THEN st1.balance END) as ledger_balance'),
                DB::raw('COALESCE(SUM(st2.debit) - SUM(st2.credit), 0) as calculated_balance'),
            ])
            ->join(DB::raw('(SELECT supplier_id, site_id, MAX(id) as max_id FROM supplier_transactions GROUP BY supplier_id, site_id) as latest'), function ($join) {
                $join->on('st1.supplier_id', '=', 'latest.supplier_id')
                    ->on('st1.site_id', '=', 'latest.site_id')
                    ->on('st1.id', '=', 'latest.max_id');
            })
            ->leftJoin('supplier_transactions as st2', function ($join) {
                $join->on('st1.supplier_id', '=', 'st2.supplier_id')
                    ->on('st1.site_id', '=', 'st2.site_id');
            })
            ->where('st2.reference_type', 'in', ['invoice', 'payment'])
            ->whereNull('st2.meta->"$.non_accounting"')
            ->groupBy('st1.supplier_id', 'st1.site_id');

        if ($supplierId) {
            $query->where('st1.supplier_id', $supplierId);
        }

        if ($siteId) {
            $query->where('st1.site_id', $siteId);
        }

        $results = $query->get();

        return $results->filter(function ($item) use ($tolerance) {
            $ledgerBalance = (float) $item->ledger_balance;
            $calculatedBalance = (float) $item->calculated_balance;
            $diff = abs($ledgerBalance - $calculatedBalance);
            return $diff > $tolerance;
        })->map(function ($item) {
            $ledgerBalance = (float) $item->ledger_balance;
            $calculatedBalance = (float) $item->calculated_balance;
            return [
                'supplier_id' => $item->supplier_id,
                'site_id' => $item->site_id,
                'ledger_balance' => $ledgerBalance,
                'calculated_balance' => $calculatedBalance,
                'drift_amount' => round($ledgerBalance - $calculatedBalance, 2),
            ];
        });
    }

    /**
     * Check 4: Payment migration traceability gaps
     */
    private function checkMigrationTraceabilityGaps()
    {
        // Check if there are payments that were transformed but not mapped
        $query = DB::table('payments_module as pm')
            ->select([
                'pm.id',
                'pm.payment_number',
                'pm.payment_type',
                'pm.purchase_order_id',
                'pm.purchase_invoice_id',
                DB::raw('CASE WHEN pmm.payment_id IS NULL THEN 1 ELSE 0 END as is_unmapped'),
            ])
            ->leftJoin('payment_migration_map as pmm', 'pm.id', '=', 'pmm.payment_id')
            ->where('pm.payment_type', 'in', ['against_invoice', 'against_po'])
            ->where('pm.created_at', '>=', '2026-04-15'); // After Phase 3 migration

        $results = $query->get();

        return $results->filter(function ($item) {
            return $item->is_unmapped === 1;
        })->map(function ($item) {
            return [
                'payment_id' => $item->id,
                'payment_number' => $item->payment_number,
                'payment_type' => $item->payment_type,
                'purchase_order_id' => $item->purchase_order_id,
                'purchase_invoice_id' => $item->purchase_invoice_id,
                'issue' => 'Payment not mapped in migration traceability table',
            ];
        });
    }

    /**
     * Output results
     */
    private function outputResults($drifts, $format)
    {
        if ($format === 'json') {
            $this->line(json_encode($drifts, JSON_PRETTY_PRINT));
            return;
        }

        $totalDrifts = 0;
        foreach ($drifts as $type => $data) {
            $totalDrifts += $data->count();
            $this->newLine();
            $this->warn("Drift Type: " . strtoupper(str_replace('_', ' ', $type)));
            
            if ($type === 'ledger_invoice_mismatch') {
                $this->table(
                    ['Supplier', 'Site', 'Ledger Balance', 'Expected', 'Drift Amount', 'Total Invoices', 'Total Payments'],
                    $data->map(function ($item) {
                        return [
                            $item['supplier_id'],
                            $item['site_id'],
                            '₹' . number_format($item['ledger_balance'], 2),
                            '₹' . number_format($item['expected_balance'], 2),
                            '₹' . number_format($item['drift_amount'], 2),
                            '₹' . number_format($item['total_invoices'], 2),
                            '₹' . number_format($item['total_payments'], 2),
                        ];
                    })->toArray()
                );
            } elseif ($type === 'adjustment_accumulation') {
                $this->table(
                    ['Supplier', 'Site', 'Adjustment Count', 'Total Amount', 'Last Date', 'Issue'],
                    $data->map(function ($item) {
                        return [
                            $item['supplier_id'],
                            $item['site_id'],
                            $item['adjustment_count'],
                            '₹' . number_format($item['total_adjustment_amount'], 2),
                            $item['last_adjustment_date'],
                            $item['issue'],
                        ];
                    })->toArray()
                );
            } elseif ($type === 'supplier_balance_inconsistency') {
                $this->table(
                    ['Supplier', 'Site', 'Ledger Balance', 'Calculated Balance', 'Drift Amount'],
                    $data->map(function ($item) {
                        return [
                            $item['supplier_id'],
                            $item['site_id'],
                            '₹' . number_format($item['ledger_balance'], 2),
                            '₹' . number_format($item['calculated_balance'], 2),
                            '₹' . number_format($item['drift_amount'], 2),
                        ];
                    })->toArray()
                );
            } elseif ($type === 'migration_traceability_gaps') {
                $this->table(
                    ['Payment ID', 'Payment Number', 'Type', 'PO ID', 'Invoice ID', 'Issue'],
                    $data->map(function ($item) {
                        return [
                            $item['payment_id'],
                            $item['payment_number'],
                            $item['payment_type'],
                            $item['purchase_order_id'],
                            $item['purchase_invoice_id'],
                            $item['issue'],
                        ];
                    })->toArray()
                );
            }
        }

        if (empty($drifts)) {
            $this->info('✅ No ledger drift detected. All balances are consistent.');
        } else {
            $this->error("❌ Found {$totalDrifts} drift issues that require investigation.");
        }
    }

    /**
     * Log summary
     */
    private function logSummary($drifts)
    {
        $totalDrifts = 0;
        foreach ($drifts as $type => $data) {
            $totalDrifts += $data->count();
        }

        Log::channel('payment_audit')->info('Ledger Drift Detection Completed', [
            'total_drifts' => $totalDrifts,
            'drifts_by_type' => array_map(fn($data) => $data->count(), $drifts),
        ]);
    }
}
