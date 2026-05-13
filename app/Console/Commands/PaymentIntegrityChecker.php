<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentIntegrityChecker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:payment-integrity 
                            {--fix : Attempt to auto-fix issues}
                            {--output=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate payment integrity: every payment has invoice mapping, no orphan allocations, no PO-based payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fix = $this->option('fix');
        $outputFormat = $this->option('output') ?? 'table';

        $this->info('Running Payment Integrity Checker...');
        $this->info('This validates: payment→invoice mapping, orphan allocations, PO-based payments');

        $issues = [];

        // Check 1: Payments without invoice
        $paymentsWithoutInvoice = $this->checkPaymentsWithoutInvoice();
        if ($paymentsWithoutInvoice->count() > 0) {
            $issues['payments_without_invoice'] = $paymentsWithoutInvoice;
        }

        // Check 2: Invoice without payment mapping
        $invoicesWithoutPayment = $this->checkInvoicesWithoutPayment();
        if ($invoicesWithoutPayment->count() > 0) {
            $issues['invoices_without_payment'] = $invoicesWithoutPayment;
        }

        // Check 3: Orphan allocations (logical)
        $orphanAllocations = $this->checkOrphanAllocations();
        if ($orphanAllocations->count() > 0) {
            $issues['orphan_allocations'] = $orphanAllocations;
        }

        // Check 4: PO-based payments (should not exist after Phase 3)
        $poBasedPayments = $this->checkPOBasedPayments();
        if ($poBasedPayments->count() > 0) {
            $issues['po_based_payments'] = $poBasedPayments;
        }

        // Check 5: Payment request without payment
        $paymentRequestsWithoutPayment = $this->checkPaymentRequestsWithoutPayment();
        if ($paymentRequestsWithoutPayment->count() > 0) {
            $issues['payment_requests_without_payment'] = $paymentRequestsWithoutPayment;
        }

        $this->outputResults($issues, $outputFormat);

        if ($fix && !empty($issues)) {
            $this->warn('Auto-fix mode enabled. Attempting to fix issues...');
            $this->attemptFixes($issues);
        }

        $this->logSummary($issues);

        return empty($issues) ? 0 : 1;
    }

    /**
     * Check 1: Payments without invoice
     */
    private function checkPaymentsWithoutInvoice()
    {
        return DB::table('payments_module as pm')
            ->select([
                'pm.id',
                'pm.payment_number',
                'pm.payment_type',
                'pm.amount',
                'pm.purchase_invoice_id',
                'pm.purchase_order_id',
                'pm.created_at',
            ])
            ->where('payment_type', 'against_invoice')
            ->whereNull('purchase_invoice_id')
            ->get();
    }

    /**
     * Check 2: Invoice without payment mapping
     */
    private function checkInvoicesWithoutPayment()
    {
        return DB::table('purchase_invoices as pi')
            ->select([
                'pi.id',
                'pi.invoice_number',
                'pi.grand_total',
                'pi.payment_status',
                'pi.created_at',
                DB::raw('COUNT(pm.id) as payment_count'),
            ])
            ->leftJoin('payments_module as pm', 'pm.purchase_invoice_id', '=', 'pi.id')
            ->where('pi.payment_status', '!=', 'paid')
            ->groupBy('pi.id', 'pi.invoice_number', 'pi.grand_total', 'pi.payment_status', 'pi.created_at')
            ->havingRaw('COUNT(pm.id) = 0')
            ->get();
    }

    /**
     * Check 3: Orphan allocations (logical check)
     */
    private function checkOrphanAllocations()
    {
        // Check if payment_module_allocations_backup has orphan entries
        // (table should be empty after Phase 3, but check backup)
        $orphans = collect();

        if (Schema::hasTable('payment_module_allocations_backup')) {
            $orphans = DB::table('payment_module_allocations_backup as pma')
                ->select([
                    'pma.id',
                    'pma.payment_module_id',
                    'pma.purchase_invoice_id',
                    'pma.purchase_order_id',
                    'pma.allocated_amount',
                ])
                ->leftJoin('payment_migration_map as pmm', 'pma.payment_module_id', '=', 'pmm.payment_id')
                ->whereNull('pmm.payment_id')
                ->get();
        }

        return $orphans;
    }

    /**
     * Check 4: PO-based payments (should not exist after Phase 3)
     */
    private function checkPOBasedPayments()
    {
        return DB::table('payments_module')
            ->select([
                'id',
                'payment_number',
                'payment_type',
                'amount',
                'purchase_order_id',
                'purchase_invoice_id',
                'created_at',
            ])
            ->whereIn('payment_type', ['against_po', 'advance_against_po'])
            ->get();
    }

    /**
     * Check 5: Payment request without payment
     */
    private function checkPaymentRequestsWithoutPayment()
    {
        if (!Schema::hasTable('payment_requests')) {
            return collect();
        }

        return DB::table('payment_requests as pr')
            ->select([
                'pr.id',
                'pr.request_number',
                'pr.requested_amount',
                'pr.status',
                'pr.approved_amount',
                'pr.created_at',
                DB::raw('COUNT(pm.id) as payment_count'),
            ])
            ->leftJoin('payments_module as pm', 'pm.payment_request_id', '=', 'pr.id')
            ->whereIn('pr.status', ['approved', 'partially_approved', 'partially_paid'])
            ->groupBy('pr.id', 'pr.request_number', 'pr.requested_amount', 'pr.status', 'pr.approved_amount', 'pr.created_at')
            ->havingRaw('COUNT(pm.id) = 0')
            ->get();
    }

    /**
     * Output results
     */
    private function outputResults($issues, $format)
    {
        if ($format === 'json') {
            $this->line(json_encode($issues, JSON_PRETTY_PRINT));
            return;
        }

        $totalIssues = 0;
        foreach ($issues as $type => $data) {
            $totalIssues += $data->count();
            $this->newLine();
            $this->warn("Issue Type: " . strtoupper(str_replace('_', ' ', $type)));
            $this->table(
                ['ID', 'Number', 'Amount/Total', 'Status', 'Created At'],
                $data->map(function ($item) {
                    return [
                        $item->id,
                        $item->payment_number ?? $item->invoice_number ?? $item->request_number ?? 'N/A',
                        '₹' . number_format($item->amount ?? $item->grand_total ?? $item->requested_amount ?? 0, 2),
                        $item->payment_type ?? $item->payment_status ?? $item->status ?? 'N/A',
                        $item->created_at ?? 'N/A',
                    ];
                })->toArray()
            );
        }

        if (empty($issues)) {
            $this->info('✅ No integrity issues found. All payments are properly mapped to invoices.');
        } else {
            $this->error("❌ Found {$totalIssues} integrity issues that require attention.");
        }
    }

    /**
     * Attempt to fix issues
     */
    private function attemptFixes($issues)
    {
        // Fix 1: Mark orphan payment requests as requiring review
        if (isset($issues['payment_requests_without_payment'])) {
            foreach ($issues['payment_requests_without_payment'] as $pr) {
                DB::table('payment_requests')
                    ->where('id', $pr->id)
                    ->update([
                        'status' => 'requires_review',
                        'rejection_reason' => 'Auto-detected: No payment created after approval',
                    ]);
            }
            $this->info("Fixed {$issues['payment_requests_without_payment']->count()} payment requests by marking as requires_review");
        }

        // Note: Other issues require manual review and cannot be auto-fixed
        if (isset($issues['payments_without_invoice'])) {
            $this->warn("Payments without invoice require manual review - cannot auto-fix");
        }

        if (isset($issues['po_based_payments'])) {
            $this->warn("PO-based payments require Phase 3 migration - cannot auto-fix");
        }
    }

    /**
     * Log summary
     */
    private function logSummary($issues)
    {
        $totalIssues = 0;
        foreach ($issues as $type => $data) {
            $totalIssues += $data->count();
        }

        Log::channel('payment_audit')->info('Payment Integrity Check Completed', [
            'total_issues' => $totalIssues,
            'issues_by_type' => array_map(fn($data) => $data->count(), $issues),
            'fix_attempted' => $this->option('fix'),
        ]);
    }
}
