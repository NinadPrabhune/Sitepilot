<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconciliationReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:reconciliation 
                            {--output=table : Output format (table, json, csv)}
                            {--supplier= : Filter by supplier ID}
                            {--site= : Filter by site ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate PO vs Invoice Reconciliation Report to detect financial inconsistencies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $outputFormat = $this->option('output') ?? 'table';
        $supplierId = $this->option('supplier');
        $siteId = $this->option('site');

        $this->info('Generating PO vs Invoice Reconciliation Report...');
        $this->info('This report compares PO totals, Invoice totals, Payment totals, and Ledger totals');

        $results = $this->generateReconciliationReport($supplierId, $siteId);

        $this->outputResults($results, $outputFormat);

        $this->logSummary($results);

        return 0;
    }

    /**
     * Generate reconciliation report
     */
    private function generateReconciliationReport($supplierId = null, $siteId = null)
    {
        $query = DB::table('purchase_orders as po')
            ->select([
                'po.id as po_id',
                'po.po_number',
                'po.supplier_id',
                'po.site_id',
                'po.grand_total as po_total',
                DB::raw('COALESCE(po.invoiced_amount, 0) as invoiced_amount'),
                'po.invoiced_status',
                DB::raw('COALESCE(SUM(pi.grand_total), 0) as invoice_total'),
                DB::raw('COALESCE(COUNT(DISTINCT pi.id), 0) as invoice_count'),
                DB::raw('COALESCE(SUM(pm.amount), 0) as payment_total'),
                DB::raw('COALESCE(COUNT(DISTINCT pm.id), 0) as payment_count'),
                DB::raw('COALESCE(SUM(st.debit - st.credit), 0) as ledger_balance'),
            ])
            ->leftJoin('purchase_invoices as pi', 'pi.po_id', '=', 'po.id')
            ->leftJoin('payments_module as pm', function ($join) {
                $join->on('pm.purchase_invoice_id', '=', 'pi.id')
                    ->orOn('pm.purchase_order_id', '=', 'po.id');
            })
            ->leftJoin('supplier_transactions as st', function ($join) {
                $join->on('st.reference_id', '=', 'po.id')
                    ->where('st.reference_type', '=', 'po');
            })
            ->where('po.workspace_id', getActiveWorkSpace())
            ->groupBy('po.id', 'po.po_number', 'po.supplier_id', 'po.site_id', 'po.grand_total', 'po.invoiced_amount', 'po.invoiced_status');

        if ($supplierId) {
            $query->where('po.supplier_id', $supplierId);
        }

        if ($siteId) {
            $query->where('po.site_id', $siteId);
        }

        $results = $query->get()->map(function ($item) {
            // Calculate reconciliation status
            $poTotal = (float) $item->po_total;
            $invoicedAmount = (float) $item->invoiced_amount;
            $invoiceTotal = (float) $item->invoice_total;
            $paymentTotal = (float) $item->payment_total;
            $ledgerBalance = (float) $item->ledger_balance;

            // Determine status
            $status = 'matched';
            $issues = [];

            // Check PO vs Invoiced Amount
            if (abs($poTotal - $invoicedAmount) > 0.01) {
                $status = 'mismatched';
                $issues[] = 'PO total ≠ invoiced amount';
            }

            // Check Invoiced Amount vs Invoice Total
            if (abs($invoicedAmount - $invoiceTotal) > 0.01) {
                $status = 'mismatched';
                $issues[] = 'Invoiced amount ≠ invoice total';
            }

            // Check Payment Total vs Invoiced Amount
            if (abs($paymentTotal - $invoicedAmount) > 0.01) {
                $status = 'mismatched';
                $issues[] = 'Payment total ≠ invoiced amount';
            }

            // Check Ledger Balance
            if (abs($ledgerBalance - ($poTotal - $paymentTotal)) > 0.01) {
                $status = 'mismatched';
                $issues[] = 'Ledger balance mismatch';
            }

            // Check for orphan status
            if ($item->invoice_count === 0 && $item->payment_count > 0) {
                $status = 'orphan';
                $issues[] = 'Payments without invoices';
            }

            return [
                'po_id' => $item->po_id,
                'po_number' => $item->po_number,
                'supplier_id' => $item->supplier_id,
                'site_id' => $item->site_id,
                'po_total' => $poTotal,
                'invoiced_amount' => $invoicedAmount,
                'invoice_total' => $invoiceTotal,
                'invoice_count' => $item->invoice_count,
                'payment_total' => $paymentTotal,
                'payment_count' => $item->payment_count,
                'ledger_balance' => $ledgerBalance,
                'status' => $status,
                'issues' => implode(', ', $issues),
                'po_diff' => round($poTotal - $invoicedAmount, 2),
                'invoice_diff' => round($invoicedAmount - $invoiceTotal, 2),
                'payment_diff' => round($paymentTotal - $invoicedAmount, 2),
            ];
        });

        return $results;
    }

    /**
     * Output results in specified format
     */
    private function outputResults($results, $format)
    {
        if ($format === 'json') {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            return;
        }

        if ($format === 'csv') {
            $this->outputCsv($results);
            return;
        }

        // Table format (default)
        $this->table(
            ['PO #', 'Supplier', 'Site', 'PO Total', 'Invoiced', 'Invoices', 'Payments', 'Ledger', 'Status', 'Issues'],
            $results->map(function ($item) {
                return [
                    $item['po_number'],
                    $item['supplier_id'],
                    $item['site_id'],
                    '₹' . number_format($item['po_total'], 2),
                    '₹' . number_format($item['invoiced_amount'], 2),
                    $item['invoice_count'],
                    $item['payment_count'],
                    '₹' . number_format($item['ledger_balance'], 2),
                    $this->formatStatus($item['status']),
                    $item['issues'],
                ];
            })->toArray()
        );

        // Summary statistics
        $total = $results->count();
        $matched = $results->where('status', 'matched')->count();
        $mismatched = $results->where('status', 'mismatched')->count();
        $orphan = $results->where('status', 'orphan')->count();

        $this->newLine();
        $this->info('Summary Statistics:');
        $this->table(
            ['Total POs', 'Matched', 'Mismatched', 'Orphan', 'Match Rate'],
            [[
                $total,
                $matched,
                $mismatched,
                $orphan,
                $total > 0 ? round(($matched / $total) * 100, 2) . '%' : '0%',
            ]]
        );
    }

    /**
     * Output CSV format
     */
    private function outputCsv($results)
    {
        $headers = ['PO Number', 'Supplier ID', 'Site ID', 'PO Total', 'Invoiced Amount', 'Invoice Total', 'Invoice Count', 'Payment Total', 'Payment Count', 'Ledger Balance', 'Status', 'Issues'];
        $this->line(implode(',', $headers));

        foreach ($results as $item) {
            $row = [
                $item['po_number'],
                $item['supplier_id'],
                $item['site_id'],
                $item['po_total'],
                $item['invoiced_amount'],
                $item['invoice_total'],
                $item['invoice_count'],
                $item['payment_total'],
                $item['payment_count'],
                $item['ledger_balance'],
                $item['status'],
                '"' . $item['issues'] . '"',
            ];
            $this->line(implode(',', $row));
        }
    }

    /**
     * Format status with color
     */
    private function formatStatus($status)
    {
        return match ($status) {
            'matched' => '<fg=green>✓ MATCHED</>',
            'mismatched' => '<fg=yellow>⚠ MISMATCHED</>',
            'orphan' => '<fg=red>✗ ORPHAN</>',
            default => $status,
        };
    }

    /**
     * Log summary to audit log
     */
    private function logSummary($results)
    {
        $total = $results->count();
        $matched = $results->where('status', 'matched')->count();
        $mismatched = $results->where('status', 'mismatched')->count();
        $orphan = $results->where('status', 'orphan')->count();

        Log::channel('payment_audit')->info('Reconciliation Report Generated', [
            'total_pos' => $total,
            'matched' => $matched,
            'mismatched' => $mismatched,
            'orphan' => $orphan,
            'match_rate' => $total > 0 ? round(($matched / $total) * 100, 2) : 0,
            'mismatched_details' => $results->where('status', 'mismatched')->take(10)->toArray(),
        ]);

        if ($mismatched > 0 || $orphan > 0) {
            $this->warn("Found {$mismatched} mismatched and {$orphan} orphan entries. Review required.");
        } else {
            $this->info('All entries matched successfully.');
        }
    }
}
