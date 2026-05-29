<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SupplierAdvance;
use App\Models\AdvanceUtilization;
use App\Models\AdvanceAdjustment;
use App\Models\PaymentsModule;
use App\Models\PaymentRequest;
use App\Models\SupplierTransaction;
use App\Models\PurchaseInvoice;

class SupplierAdvanceRepairHistory extends Command
{
    protected $signature = 'supplier-advance:repair-history
        {--supplier_id= : Supplier ID to repair}
        {--site_id= : Site ID to filter}
        {--from_date= : Start date for analysis (YYYY-MM-DD)}
        {--to_date= : End date for analysis (YYYY-MM-DD)}
        {--dry-run : Only analyze, do not apply changes}
        {--apply : Apply changes (required to make modifications)}
        {--report : Generate detailed repair report}';

    protected $description = 'Safely repair historical supplier advance data (idempotent, dry-run by default)';

    protected $findings = [];
    protected $repairedIds = [];
    protected $skippedIds = [];
    protected $failedIds = [];
    protected $dryRun = true;

    public function handle()
    {
        $this->dryRun = !$this->option('apply');
        $supplierId = $this->option('supplier_id');
        $siteId = $this->option('site_id');
        $fromDate = $this->option('from_date');
        $toDate = $this->option('to_date') ?? now()->toDateString();

        $this->info('=== SUPPLIER ADVANCE HISTORY REPAIR ===');
        $this->info($this->dryRun ? 'MODE: DRY-RUN (analysis only)' : 'MODE: APPLY CHANGES');
        $this->newLine();

        if ($supplierId) {
            $this->info("Target Supplier ID: {$supplierId}");
        }
        if ($siteId) {
            $this->info("Target Site ID: {$siteId}");
        }
        if ($fromDate) {
            $this->info("Date Range: {$fromDate} to {$toDate}");
        }
        $this->newLine();

        $this->info('PHASE 1 — ANALYZING RECORDS...');
        $this->newLine();

        $this->analyzeMissingLedgerEntries($supplierId, $siteId, $fromDate, $toDate);
        $this->analyzeIncorrectUtilizedAmounts($supplierId, $siteId, $fromDate, $toDate);
        $this->analyzeMissingUtilizationRecords($supplierId, $siteId, $fromDate, $toDate);
        $this->analyzeDuplicateUtilizationRecords($supplierId, $siteId, $fromDate, $toDate);
        $this->analyzeOverUtilizedAdvances($supplierId, $siteId, $fromDate, $toDate);
        $this->analyzeIncorrectLedgerAmounts($supplierId, $siteId, $fromDate, $toDate);
        $this->analyzeDuplicateLedgerEntries($supplierId, $siteId, $fromDate, $toDate);
        $this->analyzeLedgerBalanceMismatches($supplierId, $siteId, $fromDate, $toDate);

        $this->printFindingsSummary();

        if ($this->option('report')) {
            $this->printDetailedReport();
        }

        $this->printRepairPlan();

        if ($this->dryRun) {
            $this->newLine();
            $this->warn('DRY RUN COMPLETE - No changes made');
            $this->line('Run with --apply to execute repairs');
            return 0;
        }

        $this->newLine();
        $this->info('PHASE 3 — EXECUTING REPAIRS...');
        $this->newLine();

        $this->executeRepairs($supplierId, $siteId, $fromDate, $toDate);

        $this->printFinalSummary();
        return 0;
    }

    protected function analyzeMissingLedgerEntries(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('A. Advances missing ledger entries...');

        $query = SupplierAdvance::query()
            ->whereIn('status', [SupplierAdvance::STATUS_APPROVED, SupplierAdvance::STATUS_PAID])
            ->whereNull('deleted_at');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($fromDate) {
            $query->whereBetween('advance_date', [$fromDate, $toDate]);
        }

        $advances = $query->get();
        $missingLedger = [];

        foreach ($advances as $advance) {
            $hasLedger = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_ADVANCE)
                ->where('reference_id', $advance->id)
                ->exists();

            if (!$hasLedger) {
                $missingLedger[] = [
                    'advance_id' => $advance->id,
                    'advance_number' => $advance->advance_number,
                    'supplier_id' => $advance->supplier_id,
                    'site_id' => $advance->site_id,
                    'amount' => $advance->amount,
                    'advance_date' => $advance->advance_date,
                    'status' => $advance->status,
                ];
            }
        }

        $this->findings['missing_ledger_entries'] = $missingLedger;

        if (empty($missingLedger)) {
            $this->info('  ✓ All advances have ledger entries');
        } else {
            $this->warn("  ⚠ Found " . count($missingLedger) . " advances missing ledger entries");
            foreach ($missingLedger as $item) {
                $this->line("    - Advance #{$item['advance_number']} (ID: {$item['advance_id']}) - Amount: ₹{$item['amount']}");
            }
        }
        $this->newLine();
    }

    protected function analyzeIncorrectUtilizedAmounts(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('B. Advances with incorrect utilized_amount...');

        $query = SupplierAdvance::query()->whereNull('deleted_at');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($fromDate) {
            $query->whereBetween('advance_date', [$fromDate, $toDate]);
        }

        $advances = $query->get(['id', 'advance_number', 'amount', 'utilized_amount', 'supplier_id', 'site_id', 'advance_date']);
        $incorrect = [];

        foreach ($advances as $advance) {
            $sumUtilized = AdvanceUtilization::withTrashed()
                ->where('supplier_advance_id', $advance->id)
                ->sum('utilized_amount');

            $difference = abs($advance->utilized_amount - $sumUtilized);

            if ($difference > 0.01) {
                $incorrect[] = [
                    'advance_id' => $advance->id,
                    'advance_number' => $advance->advance_number,
                    'supplier_id' => $advance->supplier_id,
                    'site_id' => $advance->site_id,
                    'amount' => $advance->amount,
                    'recorded_utilized' => $advance->utilized_amount,
                    'actual_utilized' => $sumUtilized,
                    'difference' => $difference,
                ];
            }
        }

        $this->findings['incorrect_utilized_amounts'] = $incorrect;

        if (empty($incorrect)) {
            $this->info('  ✓ All utilized_amount values are correct');
        } else {
            $this->warn("  ⚠ Found " . count($incorrect) . " advances with incorrect utilized_amount");
            foreach ($incorrect as $item) {
                $this->line("    - Advance #{$item['advance_number']} (ID: {$item['advance_id']})");
                $this->line("      Recorded: ₹{$item['recorded_utilized']}, Actual: ₹{$item['actual_utilized']}, Diff: ₹{$item['difference']}");
            }
        }
        $this->newLine();
    }

    protected function analyzeMissingUtilizationRecords(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('C. Payments with advance deduction but missing utilization records...');

        // Get all advance payments with or without invoices
        $query = PaymentsModule::query()
            ->where('payment_type', PaymentsModule::PAYMENT_TYPE_ADVANCE_AGAINST_PO);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($fromDate) {
            $query->whereBetween('payment_date', [$fromDate, $toDate]);
        }

        $payments = $query->get(['id', 'payment_number', 'supplier_id', 'site_id', 'amount', 'payment_date', 'purchase_order_id', 'purchase_invoice_id']);

        // Get advance payments that already have a corresponding SupplierAdvance record
        // For legacy advance_against_po payments, check if there's a matching advance by po_id + amount
        $advancePaymentIds = DB::table('supplier_advances')
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->when($siteId, fn($q) => $q->where('site_id', $siteId))
            ->pluck('po_id', 'amount')
            ->toArray();

        // Also check payments_module_id linkage in advance_utilizations
        $paymentsWithUtilization = AdvanceUtilization::withTrashed()
            ->pluck('payments_module_id')
            ->unique()
            ->toArray();

        $missingUtilization = [];
        foreach ($payments as $payment) {
            $hasUtilization = in_array($payment->id, $paymentsWithUtilization);
            $hasAdvanceRecord = DB::table('supplier_advances')
                ->where('po_id', $payment->purchase_order_id)
                ->where('amount', $payment->amount)
                ->exists();

            if (!$hasUtilization && !$hasAdvanceRecord) {
                $missingUtilization[] = [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'supplier_id' => $payment->supplier_id,
                    'site_id' => $payment->site_id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'po_id' => $payment->purchase_order_id,
                    'invoice_id' => $payment->purchase_invoice_id,
                    'has_invoice' => !empty($payment->purchase_invoice_id),
                ];
            }
        }

        $this->findings['missing_utilization_records'] = $missingUtilization;

        if (empty($missingUtilization)) {
            $this->info('  ✓ All advance payments have utilization records');
        } else {
            $this->warn("  ⚠ Found " . count($missingUtilization) . " payments missing utilization records");
            foreach ($missingUtilization as $item) {
                $this->line("    - Payment #{$item['payment_number']} (ID: {$item['payment_id']}) - Amount: ₹{$item['amount']}");
            }
        }
        $this->newLine();
    }

    protected function analyzeIncorrectLedgerAmounts(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('F. Payments with incorrect ledger credit amounts...');

        $query = PaymentsModule::query()->where('payment_type', 'against_invoice');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($fromDate) {
            $query->whereBetween('payment_date', [$fromDate, $toDate]);
        }

        $payments = $query->get(['id', 'payment_number', 'amount', 'supplier_id', 'site_id', 'payment_date']);

        $incorrect = [];
        foreach ($payments as $payment) {
            // Get both payment ledger credit and any correction adjustments
            $ledgerCredit = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_PAYMENT)
                ->where('reference_id', $payment->id)
                ->sum('credit');

            $correctionCredit = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_ADJUSTMENT)
                ->where('reference_id', $payment->id)
                ->whereRaw("JSON_EXTRACT(meta, '$.adjustment_type') = 'payment_credit_correction'")
                ->sum('credit');

            $correctionDebit = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_ADJUSTMENT)
                ->where('reference_id', $payment->id)
                ->whereRaw("JSON_EXTRACT(meta, '$.adjustment_type') = 'payment_credit_correction'")
                ->sum('debit');

            $totalCredit = $ledgerCredit + $correctionCredit - $correctionDebit;

            $difference = abs($payment->amount - $totalCredit);

            if ($difference > 0.01) {
                $incorrect[] = [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'supplier_id' => $payment->supplier_id,
                    'site_id' => $payment->site_id,
                    'amount' => $payment->amount,
                    'ledger_credit' => $totalCredit,
                    'difference' => $difference,
                ];
            }
        }

        $this->findings['incorrect_ledger_amounts'] = $incorrect;

        if (empty($incorrect)) {
            $this->info('  ✓ All payment ledger amounts are correct');
        } else {
            $this->warn("  ⚠ Found " . count($incorrect) . " payments with incorrect ledger credit amounts");
            foreach ($incorrect as $item) {
                $this->line("    - Payment #{$item['payment_number']} (ID: {$item['payment_id']}) - Expected: ₹{$item['amount']}, Actual: ₹{$item['ledger_credit']}");
            }
        }
        $this->newLine();
    }

    protected function fixIncorrectLedgerAmounts(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $incorrect = $this->findings['incorrect_ledger_amounts'] ?? [];

        if (empty($incorrect)) {
            $this->info('No incorrect ledger amounts to fix');
            return;
        }

        $fixed = 0;
        $skipped = 0;

        foreach ($incorrect as $item) {
            try {
                DB::transaction(function () use ($item, &$fixed, &$skipped) {
                    $payment = PaymentsModule::lockForUpdate()->findOrFail($item['payment_id']);

                    $ledger = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_PAYMENT)
                        ->where('reference_id', $payment->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$ledger) {
                        $this->line("  Skipped payment #{$item['payment_number']} - no ledger entry found");
                        $skipped++;
                        return;
                    }

                    $difference = $payment->amount - $ledger->credit;

                    // Create adjustment entry to fix the difference
                    SupplierTransaction::create([
                        'supplier_id' => $payment->supplier_id,
                        'site_id' => $payment->site_id,
                        'reference_type' => SupplierTransaction::TYPE_ADJUSTMENT,
                        'reference_id' => $payment->id,
                        'reference_amount' => abs($difference),
                        'transaction_date' => $payment->payment_date,
                        'transaction_datetime' => $payment->payment_date,
                        'debit' => $difference < 0 ? abs($difference) : 0,
                        'credit' => $difference > 0 ? abs($difference) : 0,
                        'balance' => $ledger->balance + $difference,
                        'description' => "Adjustment for PAY-{$item['payment_number']} - corrected credit to match payment amount",
                        'meta' => [
                            'adjustment_type' => 'payment_credit_correction',
                            'original_credit' => $ledger->credit,
                            'corrected_credit' => $payment->amount,
                            'repair_timestamp' => now()->toIso8601String(),
                        ],
                        'workspace_id' => $payment->workspace_id,
                        'created_by' => 1,
                    ]);

                    Log::channel('payment_audit')->info('Historical repair: created adjustment for incorrect ledger credit', [
                        'payment_id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'original_credit' => $ledger->credit,
                        'difference' => $difference,
                        'repair_type' => 'incorrect_ledger_credit_correction',
                    ]);

                    $this->line("  ✓ Created adjustment for payment #{$item['payment_number']} (diff: ₹{$difference})");
                    $fixed++;
                }, 5);
            } catch (\Exception $e) {
                Log::error('Failed to fix incorrect ledger amount', [
                    'payment_id' => $item['payment_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Created {$fixed} adjustment entries for incorrect ledger amounts (skipped {$skipped})");
        $this->newLine();
    }

    protected function analyzeDuplicateUtilizationRecords(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('D. Duplicate utilization records...');

        $advanceIds = $this->getFilteredAdvanceIds($supplierId, $siteId, $fromDate, $toDate);

        $duplicates = DB::table('advance_utilizations as au')
            ->select('supplier_advance_id', 'purchase_invoice_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(utilized_amount) as total_utilized'))
            ->whereIn('supplier_advance_id', $advanceIds)
            ->groupBy('supplier_advance_id', 'purchase_invoice_id')
            ->having('count', '>', 1)
            ->get();

        $dedupeList = [];
        foreach ($duplicates as $dup) {
            $advance = SupplierAdvance::find($dup->supplier_advance_id);
            $invoice = PurchaseInvoice::find($dup->purchase_invoice_id);
            $dedupeList[] = [
                'advance_id' => $dup->supplier_advance_id,
                'advance_number' => $advance?->advance_number,
                'invoice_id' => $dup->purchase_invoice_id,
                'invoice_number' => $invoice?->invoice_number,
                'duplicate_count' => $dup->count,
                'total_utilized' => $dup->total_utilized,
            ];
        }

        $this->findings['duplicate_utilizations'] = $dedupeList;

        if (empty($dedupeList)) {
            $this->info('  ✓ No duplicate utilization records found');
        } else {
            $this->warn("  ⚠ Found " . count($dedupeList) . " duplicate utilization groups");
            foreach ($dedupeList as $item) {
                $this->line("    - Advance #{$item['advance_number']} -> Invoice #{$item['invoice_number']} ({$item['duplicate_count']} duplicates)");
            }
        }
        $this->newLine();
    }

    protected function analyzeOverUtilizedAdvances(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('E. Over-utilized advances (utilized_amount > amount)...');

        $query = SupplierAdvance::query()->whereNull('deleted_at');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($fromDate) {
            $query->whereBetween('advance_date', [$fromDate, $toDate]);
        }

        $overUtilized = [];
        foreach ($query->get(['id', 'advance_number', 'amount', 'utilized_amount', 'supplier_id', 'site_id']) as $advance) {
            if ($advance->utilized_amount > $advance->amount) {
                $overUtilized[] = [
                    'advance_id' => $advance->id,
                    'advance_number' => $advance->advance_number,
                    'supplier_id' => $advance->supplier_id,
                    'site_id' => $advance->site_id,
                    'amount' => $advance->amount,
                    'utilized_amount' => $advance->utilized_amount,
                    'over_by' => $advance->utilized_amount - $advance->amount,
                ];
            }
        }

        $this->findings['over_utilized'] = $overUtilized;

        if (empty($overUtilized)) {
            $this->info('  ✓ No over-utilized advances found');
        } else {
            $this->error("  ✗ Found " . count($overUtilized) . " over-utilized advances - REQUIRES MANUAL REVIEW");
            foreach ($overUtilized as $item) {
                $this->line("    - Advance #{$item['advance_number']} (ID: {$item['advance_id']})");
                $this->line("      Amount: ₹{$item['amount']}, Utilized: ₹{$item['utilized_amount']}, Over by: ₹{$item['over_by']}");
            }
        }
        $this->newLine();
    }

    protected function analyzeLedgerBalanceMismatches(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('F. Ledger balance mismatches...');

        $mismatches = [];

        if ($supplierId) {
            $mismatches = $this->analyzeSupplierBalances($supplierId, $siteId, $fromDate, $toDate);
        } else {
            // Get all unique suppliers in date range
            $supplierList = SupplierTransaction::query()
                ->when($siteId, fn($q) => $q->where('site_id', $siteId))
                ->when($fromDate, fn($q) => $q->whereDate('transaction_date', '>=', $fromDate))
                ->whereDate('transaction_date', '<=', $toDate)
                ->distinct('supplier_id')
                ->pluck('supplier_id');

            foreach ($supplierList as $sid) {
                $supplierMismatches = $this->analyzeSupplierBalances($sid, $siteId, $fromDate, $toDate);
                $mismatches = array_merge($mismatches, $supplierMismatches);
            }
        }

        $this->findings['balance_mismatches'] = $mismatches;

        if (empty($mismatches)) {
            $this->info('  ✓ All ledger balances are consistent');
        } else {
            $this->warn("  ⚠ Found " . count($mismatches) . " balance mismatches");
            foreach ($mismatches as $item) {
                $this->line("    - Transaction ID {$item['transaction_id']} ({$item['reference_type']}) - Expected: {$item['expected_balance']}, Actual: {$item['actual_balance']}");
            }
        }
        $this->newLine();
    }

    protected function analyzeSupplierBalances(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): array
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($fromDate) {
            $query->whereDate('transaction_date', '>=', $fromDate);
        }
        $query->whereDate('transaction_date', '<=', $toDate);

        $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc');

        $mismatches = [];
        $runningBalance = 0;
        $ignoredTypes = [SupplierTransaction::TYPE_PO, SupplierTransaction::TYPE_GRN];

        $query->chunkById(100, function ($transactions) use (&$mismatches, &$runningBalance, $ignoredTypes) {
            foreach ($transactions as $t) {
                $meta = is_array($t->meta) ? $t->meta : json_decode($t->meta ?? '{}', true);
                $isNonAccounting = in_array($t->reference_type, $ignoredTypes) || !empty($meta['non_accounting']);

                if (!$isNonAccounting) {
                    $runningBalance = (float)$runningBalance + (float)$t->debit - (float)$t->credit;
                }

                if (abs($runningBalance - (float)$t->balance) > 0.01) {
                    $mismatches[] = [
                        'transaction_id' => $t->id,
                        'reference_type' => $t->reference_type,
                        'reference_id' => $t->reference_id,
                        'expected_balance' => $runningBalance,
                        'actual_balance' => $t->balance,
                    ];
                }
            }
        });

        return $mismatches;
    }

    protected function printFindingsSummary(): void
    {
        $this->info('=== ANALYSIS SUMMARY ===');
        $this->line('A. Missing ledger entries: ' . count($this->findings['missing_ledger_entries'] ?? []));
        $this->line('B. Incorrect utilized_amount: ' . count($this->findings['incorrect_utilized_amounts'] ?? []));
        $this->line('C. Missing utilization records: ' . count($this->findings['missing_utilization_records'] ?? []));
        $this->line('D. Duplicate utilizations: ' . count($this->findings['duplicate_utilizations'] ?? []));
        $this->line('E. Over-utilized advances: ' . count($this->findings['over_utilized'] ?? []));
        $this->line('F. Incorrect ledger amounts: ' . count($this->findings['incorrect_ledger_amounts'] ?? []));
        $this->line('G. Balance mismatches: ' . count($this->findings['balance_mismatches'] ?? []));
        $this->line('H. Duplicate ledger entries: ' . count($this->findings['duplicate_ledger_entries'] ?? []));
        $this->newLine();
    }

    protected function printDetailedReport(): void
    {
        $this->newLine();
        $this->info('=== DETAILED REPAIR PLAN ===');
        $this->newLine();

        foreach (['missing_ledger_entries', 'incorrect_utilized_amounts', 'missing_utilization_records', 'duplicate_utilizations', 'over_utilized', 'incorrect_ledger_amounts', 'balance_mismatches', 'duplicate_ledger_entries'] as $category) {
            $items = $this->findings[$category] ?? [];
            if (empty($items)) continue;

            $this->line("--- {$category} ---");
            foreach ($items as $i => $item) {
                $this->line(($i + 1) . ". ");
                foreach ($item as $key => $value) {
                    if (in_array($key, ['difference', 'over_by', 'total_utilized'])) {
                        $this->line("   {$key}: ₹{$value}");
                    } else {
                        $this->line("   {$key}: {$value}");
                    }
                }
            }
            $this->newLine();
        }
    }

    protected function printRepairPlan(): void
    {
        $this->info('=== REPAIR PLAN ===');
        $this->newLine();

        $plan = [];

        if (!empty($this->findings['missing_ledger_entries'])) {
            $plan[] = [
                'action' => 'CREATE_MISSING_LEDGER_ENTRIES',
                'count' => count($this->findings['missing_ledger_entries']),
                'risk' => 'LOW',
                'reason' => 'Paid advances without ledger entries will get historical ledger entries',
            ];
        }

        if (!empty($this->findings['incorrect_utilized_amounts'])) {
            $plan[] = [
                'action' => 'REBUILD_UTILIZED_AMOUNTS',
                'count' => count($this->findings['incorrect_utilized_amounts']),
                'risk' => 'MEDIUM',
                'reason' => 'utilized_amount will be recalculated from advance_utilizations table',
            ];
        }

        if (!empty($this->findings['missing_utilization_records'])) {
            $plan[] = [
                'action' => 'CREATE_MISSING_UTILIZATION_RECORDS',
                'count' => count($this->findings['missing_utilization_records']),
                'risk' => 'HIGH',
                'reason' => 'Payment records will be reconstructed as AdvanceAdjustment entries',
            ];
        }

        if (!empty($this->findings['duplicate_utilizations'])) {
            $plan[] = [
                'action' => 'DEDUPLICATE_UTILIZATION_RECORDS',
                'count' => count($this->findings['duplicate_utilizations']),
                'risk' => 'MEDIUM',
                'reason' => 'Soft-deleted duplicates will remain; only valid record preserved',
            ];
        }

        if (!empty($this->findings['over_utilized'])) {
            $plan[] = [
                'action' => 'MANUAL_REVIEW_REQUIRED',
                'count' => count($this->findings['over_utilized']),
                'risk' => 'CRITICAL',
                'reason' => 'These advances are over-utilized - NO AUTO-REPAIR will occur',
            ];
        }

        if (!empty($this->findings['balance_mismatches'])) {
            $plan[] = [
                'action' => 'RECALCULATE_LEDGER_BALANCES',
                'count' => count($this->findings['balance_mismatches']),
                'risk' => 'LOW',
                'reason' => 'Ledger balances will be recalculated in transaction',
            ];
        }

        if (!empty($this->findings['incorrect_ledger_amounts'])) {
            $plan[] = [
                'action' => 'FIX_INCORRECT_LEDGER_AMOUNTS',
                'count' => count($this->findings['incorrect_ledger_amounts']),
                'risk' => 'MEDIUM',
                'reason' => 'Adjustment entries will be created for payments with mismatched ledger credits',
            ];
        }

        if (!empty($this->findings['duplicate_ledger_entries'])) {
            $plan[] = [
                'action' => 'REMOVE_DUPLICATE_LEDGER_ENTRIES',
                'count' => count($this->findings['duplicate_ledger_entries']),
                'risk' => 'MEDIUM',
                'reason' => 'Reversal entries will be created for duplicate ledger entries',
            ];
        }

        foreach ($plan as $step) {
            $riskColor = match($step['risk']) {
                'LOW' => 'info',
                'MEDIUM' => 'warn',
                'HIGH' => 'error',
                'CRITICAL' => 'error',
            };
            $this->$riskColor("  {$step['action']}: {$step['count']} records - Risk: {$step['risk']}");
            $this->line("    Reason: {$step['reason']}");
            $this->newLine();
        }
    }

    protected function executeRepairs(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        if (!empty($this->findings['over_utilized'])) {
            $this->error('CRITICAL: Over-utilized advances require manual review before proceeding');
            $this->line('Skipping repairs due to critical data integrity issues');
            foreach ($this->findings['over_utilized'] as $item) {
                $this->failedIds[] = $item['advance_id'];
            }
            return;
        }

        $this->createMissingLedgerEntries($supplierId, $siteId, $fromDate, $toDate);
        $this->rebuildUtilizedAmounts($supplierId, $siteId, $fromDate, $toDate);
        $this->createMissingUtilizationRecords($supplierId, $siteId, $fromDate, $toDate);
        $this->fixIncorrectLedgerAmounts($supplierId, $siteId, $fromDate, $toDate);
        $this->removeDuplicateLedgerEntries($supplierId, $siteId, $fromDate, $toDate);
        $this->recalculateLedgerBalances($supplierId, $siteId, $fromDate, $toDate);
    }

    protected function createMissingLedgerEntries(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $missingLedger = $this->findings['missing_ledger_entries'] ?? [];

        if (empty($missingLedger)) {
            $this->info('No missing ledger entries to create');
            return;
        }

        $created = 0;
        $skipped = 0;
        foreach ($missingLedger as $item) {
            try {
                DB::transaction(function () use ($item, &$created, &$skipped) {
                    $advance = SupplierAdvance::lockForUpdate()->findOrFail($item['advance_id']);

                    $existing = SupplierTransaction::where('reference_type', SupplierTransaction::TYPE_ADVANCE)
                        ->where('reference_id', $advance->id)
                        ->exists();

                    if ($existing) {
                        $this->line("  Skipped advance #{$item['advance_number']} - ledger already exists");
                        $this->skippedIds[] = $item['advance_id'];
                        $skipped++;
                        return;
                    }

                    $userId = $advance->created_by ?? $advance->approved_by ?? 1;

                    SupplierTransaction::create([
                        'supplier_id' => $advance->supplier_id,
                        'site_id' => $advance->site_id,
                        'reference_type' => SupplierTransaction::TYPE_ADVANCE,
                        'reference_id' => $advance->id,
                        'reference_amount' => $advance->amount,
                        'transaction_date' => $advance->payment_date ?? $advance->advance_date,
                        'transaction_datetime' => $advance->payment_date ?? $advance->advance_date,
                        'debit' => 0,
                        'credit' => $advance->amount,
                        'balance' => -$advance->amount,
                        'description' => "{$advance->advance_number} / Advance Payment / " . ($advance->payment_mode ?? 'Bank Transfer'),
                        'meta' => [
                            'payment_subtype' => 'advance',
                            'advance_source' => $advance->source,
                            'po_id' => $advance->po_id,
                            'repair' => true,
                            'repair_timestamp' => now()->toIso8601String(),
                        ],
                        'workspace_id' => $advance->workspace_id,
                        'created_by' => $userId,
                    ]);

                    Log::channel('payment_audit')->info('Historical repair: created missing ledger entry', [
                        'advance_id' => $advance->id,
                        'advance_number' => $advance->advance_number,
                        'amount' => $advance->amount,
                        'repair_type' => 'missing_ledger_entry',
                    ]);

                    $this->line("  ✓ Created ledger entry for advance #{$item['advance_number']}");
                    $this->repairedIds[] = $item['advance_id'];
                    $created++;
                }, 5);
            } catch (\Exception $e) {
                Log::error('Failed to create ledger entry', [
                    'advance_id' => $item['advance_id'],
                    'error' => $e->getMessage(),
                ]);
                $this->failedIds[] = $item['advance_id'];
            }
        }

        $this->info("Created {$created} missing ledger entries (skipped {$skipped})");
        $this->newLine();
    }

    protected function rebuildUtilizedAmounts(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $incorrect = $this->findings['incorrect_utilized_amounts'] ?? [];

        if (empty($incorrect)) {
            $this->info('No incorrect utilized_amount values to fix');
            return;
        }

        $fixed = 0;
        $skipped = 0;
        foreach ($incorrect as $item) {
            try {
                DB::transaction(function () use ($item, &$fixed, &$skipped) {
                    $advance = SupplierAdvance::lockForUpdate()->findOrFail($item['advance_id']);

                    $actualUtilized = AdvanceUtilization::withTrashed()
                        ->where('supplier_advance_id', $advance->id)
                        ->sum('utilized_amount');

                    if (abs($advance->utilized_amount - $actualUtilized) <= 0.01) {
                        $this->skippedIds[] = $item['advance_id'];
                        $skipped++;
                        return;
                    }

                    $advance->update(['utilized_amount' => $actualUtilized]);

                    Log::channel('payment_audit')->info('Historical repair: rebuilt utilized_amount', [
                        'advance_id' => $advance->id,
                        'advance_number' => $advance->advance_number,
                        'old_utilized' => $advance->utilized_amount,
                        'new_utilized' => $actualUtilized,
                        'repair_type' => 'utilized_amount_rebuild',
                    ]);

                    $this->line("  ✓ Fixed utilized_amount for advance #{$item['advance_number']} (₹{$item['recorded_utilized']} → ₹{$actualUtilized})");
                    $this->repairedIds[] = $item['advance_id'];
                    $fixed++;
                }, 5);
            } catch (\Exception $e) {
                Log::error('Failed to rebuild utilized_amount', [
                    'advance_id' => $item['advance_id'],
                    'error' => $e->getMessage(),
                ]);
                $this->failedIds[] = $item['advance_id'];
            }
        }

        $this->info("Rebuilt {$fixed} utilized_amount values (skipped {$skipped})");
        $this->newLine();
    }

    protected function createMissingUtilizationRecords(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $missing = $this->findings['missing_utilization_records'] ?? [];

        if (empty($missing)) {
            $this->info('No missing utilization records to create');
            return;
        }

        $created = 0;
        $skipped = 0;
        foreach ($missing as $item) {
            try {
                DB::transaction(function () use ($item, &$created, &$skipped) {
                    $payment = PaymentsModule::lockForUpdate()->findOrFail($item['payment_id']);

                    $existing = AdvanceAdjustment::where('payment_id', $payment->id)->exists();
                    $existingUtil = AdvanceUtilization::withTrashed()->where('payments_module_id', $payment->id)->exists();

                    if ($existing || $existingUtil) {
                        $this->line("  Skipped payment #{$item['payment_number']} - already has adjustment/utilization");
                        $this->skippedIds[] = $item['payment_id'];
                        $skipped++;
                        return;
                    }

                    // For advance payments, we may not have an invoice - check for payment_request_id link
                    // Advance payments link to SupplierAdvance via advance_against_po workflow
                    // If no invoice and no advance linkage, skip this payment
                    if (empty($payment->purchase_invoice_id)) {
                        $this->line("  Skipped payment #{$item['payment_number']} - no invoice (advance-only payment)");
                        $this->skippedIds[] = $item['payment_id'];
                        $skipped++;
                        return;
                    }

                    AdvanceAdjustment::create([
                        'payment_id' => $payment->id,
                        'purchase_invoice_id' => $payment->purchase_invoice_id,
                        'utilized_amount' => 0,
                    ]);

                    Log::channel('payment_audit')->info('Historical repair: created missing AdvanceAdjustment', [
                        'payment_id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'repair_type' => 'missing_utilization_record',
                    ]);

                    $this->line("  ✓ Created placeholder AdvanceAdjustment for payment #{$item['payment_number']}");
                    $this->repairedIds[] = $item['payment_id'];
                    $created++;
                }, 5);
            } catch (\Exception $e) {
                Log::error('Failed to create missing utilization', [
                    'payment_id' => $item['payment_id'],
                    'error' => $e->getMessage(),
                ]);
                $this->failedIds[] = $item['payment_id'];
            }
        }

        $this->info("Created {$created} missing utilization records (skipped {$skipped})");
        $this->newLine();
    }

    protected function recalculateLedgerBalances(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $mismatches = $this->findings['balance_mismatches'] ?? [];

        if (empty($mismatches)) {
            $this->info('No balance mismatches to fix');
            return;
        }

        $this->info('Recalculating ledger balances...');
        if ($supplierId) {
            // Full rebuild without snapshot - use direct update
            $this->fullRebuildSupplierBalance($supplierId, $siteId);
            $this->info("Recalculated ledger balances for supplier {$supplierId}");
        } else {
            // Extract unique suppliers from mismatches
            $suppliers = array_unique(array_map(function ($m) {
                return DB::table('supplier_transactions')->where('id', $m['transaction_id'])->value('supplier_id');
            }, $mismatches));

            $recalculated = 0;
            foreach ($suppliers as $sid) {
                $this->fullRebuildSupplierBalance($sid, $siteId);
                $recalculated++;
            }
            $this->info("Recalculated ledger balances for {$recalculated} suppliers");
        }
        $this->newLine();
    }

    protected function fullRebuildSupplierBalance(?int $supplierId, ?int $siteId): void
    {
        $query = SupplierTransaction::where('supplier_id', $supplierId);
        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        // Order by transaction_date then id for proper chronological balance
        $transactions = $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc')->get();

        $runningBalance = 0;
        $ignoredTypes = [SupplierTransaction::TYPE_PO, SupplierTransaction::TYPE_GRN];

        DB::transaction(function () use ($transactions, &$runningBalance, $ignoredTypes) {
            foreach ($transactions as $transaction) {
                $meta = is_array($transaction->meta) ? $transaction->meta : json_decode($transaction->meta ?? '{}', true);
                $isNonAccounting = in_array($transaction->reference_type, $ignoredTypes) || !empty($meta['non_accounting']);

                if (!$isNonAccounting) {
                    $runningBalance = (float)$runningBalance + (float)$transaction->debit - (float)$transaction->credit;
                }

                // Update balance directly
                DB::table('supplier_transactions')
                    ->where('id', $transaction->id)
                    ->update(['balance' => $runningBalance]);
            }
        });
    }

    protected function printFinalSummary(): void
    {
        $this->info('=== REPAIR COMPLETE ===');
        $this->line('Repaired IDs: ' . count($this->repairedIds));
        $this->line('Skipped IDs: ' . count($this->skippedIds));
        $this->line('Failed IDs: ' . count($this->failedIds));
        $this->newLine();

        if (!empty($this->failedIds)) {
            $this->error('Some repairs failed - check logs for details');
        }

        $this->info('Before/After verification is recommended');
    }

    protected function analyzeDuplicateLedgerEntries(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $this->info('G. Duplicate ledger entries (same reference type + ref ID)...');

        $query = SupplierTransaction::query();
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $duplicates = DB::table('supplier_transactions as st')
            ->select('reference_type', 'reference_id', 'supplier_id', 'site_id', DB::raw('COUNT(*) as count'), DB::raw('GROUP_CONCAT(id) as ids'), DB::raw('SUM(credit) as total_credit'), DB::raw('MAX(reference_amount) as expected_amount'))
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->when($siteId, fn($q) => $q->where('site_id', $siteId))
            ->when($fromDate, fn($q) => $q->whereDate('transaction_date', '>=', $fromDate))
            ->whereDate('transaction_date', '<=', $toDate)
            ->groupBy('reference_type', 'reference_id', 'supplier_id', 'site_id')
            ->having('count', '>', 1)
            ->get();

        $dedupeList = [];
        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup->ids);
            $ledgerEntries = SupplierTransaction::whereIn('id', $ids)->get();

            $dedupeList[] = [
                'reference_type' => $dup->reference_type,
                'reference_id' => $dup->reference_id,
                'supplier_id' => $dup->supplier_id,
                'site_id' => $dup->site_id,
                'duplicate_count' => $dup->count,
                'total_credit' => $dup->total_credit,
                'expected_amount' => $dup->expected_amount,
                'ids' => $ids,
                'entries' => $ledgerEntries->map(fn($e) => [
                    'id' => $e->id,
                    'credit' => $e->credit,
                    'transaction_date' => $e->transaction_date,
                ]),
            ];
        }

        $this->findings['duplicate_ledger_entries'] = $dedupeList;

        if (empty($dedupeList)) {
            $this->info('  ✓ No duplicate ledger entries found');
        } else {
            $this->warn("  ⚠ Found " . count($dedupeList) . " groups with duplicate ledger entries");
            foreach ($dedupeList as $item) {
                $this->line("    - {$item['reference_type']} #{$item['reference_id']} - {$item['duplicate_count']} entries (total credit: ₹{$item['total_credit']})");
            }
        }
        $this->newLine();
    }

    protected function removeDuplicateLedgerEntries(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): void
    {
        $duplicates = $this->findings['duplicate_ledger_entries'] ?? [];

        if (empty($duplicates)) {
            $this->info('No duplicate ledger entries to remove');
            return;
        }

        $removed = 0;
        $skipped = 0;

        foreach ($duplicates as $item) {
            $ids = $item['ids'];
            $validEntry = null;
            $entriesToRemove = [];

            // For advance-type duplicates: keep the one that matches the SupplierAdvance amount
            // For payment-type duplicates: keep the one with credit matching the payment amount
            if ($item['reference_type'] === SupplierTransaction::TYPE_ADVANCE) {
                $advance = SupplierAdvance::find($item['reference_id']);
                if ($advance) {
                    foreach ($item['entries'] as $entry) {
                        if (abs($entry['credit'] - $advance->amount) > 0.01) {
                            $entriesToRemove[] = $entry['id'];
                        } else {
                            $validEntry = $entry;
                        }
                    }
                }
            } elseif ($item['reference_type'] === SupplierTransaction::TYPE_PAYMENT) {
                $payment = PaymentsModule::find($item['reference_id']);
                if ($payment) {
                    foreach ($item['entries'] as $entry) {
                        if (abs($entry['credit'] - $payment->amount) > 0.01) {
                            $entriesToRemove[] = $entry['id'];
                        } else {
                            $validEntry = $entry;
                        }
                    }
                }
            }

            if (empty($validEntry)) {
                $this->line("  Warning: Could not determine valid entry for {$item['reference_type']} #{$item['reference_id']}");
                $skipped++;
                continue;
            }

            foreach ($entriesToRemove as $entryId) {
                try {
                    DB::transaction(function () use ($entryId, &$removed) {
                        $entry = SupplierTransaction::lockForUpdate()->findOrFail($entryId);

                        // Create reversal entry instead of deleting (immutable ledger)
                        SupplierTransaction::create([
                        'supplier_id' => $entry->supplier_id,
                        'site_id' => $entry->site_id,
                        'reference_type' => SupplierTransaction::TYPE_ADJUSTMENT,
                        'reference_id' => $entryId,
                        'reference_amount' => abs($entry->credit),
                        'transaction_date' => $entry->transaction_date,
                        'transaction_datetime' => $entry->transaction_date,
                        'debit' => $entry->credit,
                        'credit' => 0,
                        'balance' => $entry->balance + $entry->credit,
                        'description' => "Reversal of duplicate ledger entry #{$entryId}",
                        'meta' => [
                            'reversal_of' => $entryId,
                            'reason' => 'duplicate_ledger_fix',
                            'repair_timestamp' => now()->toIso8601String(),
                        ],
                        'workspace_id' => $entry->workspace_id,
                        'created_by' => 1,
                    ]);

                        Log::channel('payment_audit')->info('Historical repair: created reversal for duplicate ledger entry', [
                            'duplicate_entry_id' => $entryId,
                            'reference_type' => $entry->reference_type,
                            'reference_id' => $entry->reference_id,
                            'original_credit' => $entry->credit,
                            'repair_type' => 'duplicate_ledger_reversal',
                        ]);

                        $this->line("  ✓ Created reversal entry for duplicate ID {$entryId}");
                        $removed++;
                    }, 5);
                } catch (\Exception $e) {
                    Log::error('Failed to create reversal for duplicate entry', [
                        'entry_id' => $entryId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Created {$removed} reversal entries for duplicate ledger records (skipped {$skipped})");
        $this->newLine();
    }

    protected function getFilteredAdvanceIds(?int $supplierId, ?int $siteId, ?string $fromDate, string $toDate): array
    {
        $query = SupplierAdvance::query();

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($fromDate) {
            $query->whereBetween('advance_date', [$fromDate, $toDate]);
        }

        return $query->pluck('id')->toArray();
    }
}