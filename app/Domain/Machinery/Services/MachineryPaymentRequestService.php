<?php

namespace App\Domain\Machinery\Services;

use App\Domain\Machinery\Enums\MachineryPaymentStatus;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentPeriod;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\Machinery;
use App\Support\Finance\HandlesDeadlocks;
use App\Support\Finance\HasIdempotency;
use App\Support\Finance\PaymentAuditLogger;
use App\Support\Finance\SafeTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MachineryPaymentRequestService
{
    use HandlesDeadlocks, HasIdempotency, SafeTransaction;
    
    protected PaymentAuditLogger $auditLogger;
    
    public function __construct(PaymentAuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }
    
    /**
     * Create payment request from ledger data
     * 
     * CRITICAL: All invariants enforced here
     */
    public function createFromLedger(
        int $machineryId,
        int $supplierId,
        string $periodStart,
        string $periodEnd,
        int $requestedByUserId,
        ?string $idempotencyKey = null
    ): MachineryPaymentRequest
    {
        return $this->withDeadlockRetry(function () use (
            $machineryId, $supplierId, $periodStart, $periodEnd, $requestedByUserId, $idempotencyKey
        ) {
            return $this->safeTransaction(function () use (
                $machineryId, $supplierId, $periodStart, $periodEnd, $requestedByUserId, $idempotencyKey
            ) {
                // INVARIANT 1: Period overlap validation
                $this->validatePeriodOverlap($machineryId, $periodStart, $periodEnd);
                // Validate period overlap
                $this->validatePeriodOverlap($machineryId, $periodStart, $periodEnd);
                $this->validateActiveRequestOverlap($machineryId, $periodStart, $periodEnd);
                
                // Validate billing protection
                $billingCheck = \App\Services\MachineryBillingProtectionService::canCreatePaymentRequest($machineryId, $periodStart, $periodEnd);
                if (!$billingCheck['can_create']) {
                    throw new \RuntimeException($billingCheck['reason']);
                }
                
                // INVARIANT 3: Lock ledger entries during calculation
                $lockedEntries = $this->lockLedgerEntries($machineryId, $periodStart, $periodEnd);
                
                if ($lockedEntries->isEmpty()) {
                    throw new \RuntimeException('No eligible ledger entries found for the specified period');
                }
                
                // Idempotency check (after locking to ensure workspace_id is available)
                $workspaceId = $lockedEntries->first()->workspace_id;
                if ($existing = $this->checkIdempotency($idempotencyKey, MachineryPaymentRequest::class, 'idempotency_key', $workspaceId)) {
                    return $existing;
                }
                
                // INVARIANT 4: Financial correctness (direction-based)
                // Load machinery once to avoid N+1 queries and ensure consistent filtering
                $machinery = Machinery::find($machineryId);
                $calculation = $this->calculatePayable($lockedEntries, $machinery, Carbon::parse($periodStart), Carbon::parse($periodEnd));
                
                // INVARIANT 5: Audit reproducibility
                $auditSnapshot = $this->buildAuditSnapshot($lockedEntries, $calculation);
                
                // INVARIANT 6: Negative payable handling
                $status = $calculation['net_payable'] <= 0 
                    ? MachineryPaymentStatus::HOLD 
                    : MachineryPaymentStatus::DRAFT;
                
                if ($status === MachineryPaymentStatus::HOLD) {
                    Log::warning('Negative payable detected - status set to HOLD', [
                        'machinery_id' => $machineryId,
                        'period' => "{$periodStart} to {$periodEnd}",
                        'net_payable' => $calculation['net_payable'],
                    ]);
                }
                
                // Create payment request with idempotency handling
                try {
                    $paymentRequest = MachineryPaymentRequest::create([
                        'machinery_id' => $machineryId,
                        'supplier_id' => $supplierId,
                        'workspace_id' => $workspaceId,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'credits' => $calculation['credits'],
                        'debits' => $calculation['debits'],
                        'net_payable' => $calculation['net_payable'],
                        'status' => $status->value,
                        'audit_snapshot' => $auditSnapshot,
                        'idempotency_key' => $idempotencyKey,
                        'requested_by' => $requestedByUserId,
                        // Enhanced breakdown fields (will be stored if columns exist)
                        'gross_amount' => $calculation['gross_amount'] ?? $calculation['net_payable'],
                        'diesel_deduction' => $calculation['diesel_deduction'] ?? 0,
                        'calculation_method' => $calculation['calculation_method'] ?? 'legacy',
                        'billing_breakdown' => $calculation['billing_breakdown'] ?? null,
                        'diesel_breakdown' => $calculation['diesel_breakdown'] ?? null,
                        // Concurrency protection fields
                        'billing_month' => Carbon::parse($periodStart)->month,
                        'billing_year' => Carbon::parse($periodStart)->year,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    $paymentRequest = $this->handleIdempotencyConflict(
                        $e,
                        $workspaceId,
                        $idempotencyKey,
                        MachineryPaymentRequest::class
                    );
                    if ($paymentRequest) {
                        return $paymentRequest;
                    }
                    throw $e;
                }
                
                $this->auditLogger->logPaymentCreated('machinery', [
                    'payment_request_id' => $paymentRequest->id,
                    'machinery_id' => $machineryId,
                    'period' => "{$periodStart} to {$periodEnd}",
                    'net_payable' => $calculation['net_payable'],
                ]);
                
                return $paymentRequest;
            });
        });
    }
    
    /**
     * Calculate payment request without creating it
     * Used for preview/calculation only - does not persist to database
     */
    public function calculateOnly(
        int $machineryId,
        int $supplierId,
        string $periodStart,
        string $periodEnd
    ): array
    {
        // Get machinery
        $machinery = Machinery::find($machineryId);
        if (!$machinery) {
            throw new \RuntimeException('Machinery not found');
        }

        // Validate period overlap (read-only check)
        $this->validatePeriodOverlap($machineryId, $periodStart, $periodEnd);
        $this->validateActiveRequestOverlap($machineryId, $periodStart, $periodEnd);

        // Validate billing protection
        $billingCheck = \App\Services\MachineryBillingProtectionService::canCreatePaymentRequest($machineryId, $periodStart, $periodEnd);
        if (!$billingCheck['can_create']) {
            throw new \RuntimeException($billingCheck['reason']);
        }

        // Get ledger entries WITHOUT locking (for calculation only)
        $entries = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_reversal', false)
            ->whereNull('payment_request_id')
            ->where('amount', '>', 0)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            throw new \RuntimeException('No eligible ledger entries found for the specified period');
        }

        // Calculate payable
        $calculation = $this->calculatePayable($entries, $machinery, Carbon::parse($periodStart), Carbon::parse($periodEnd));

        // Build audit snapshot for preview
        $auditSnapshot = $this->buildAuditSnapshot($entries, $calculation);

        return [
            'machinery_id' => $machineryId,
            'supplier_id' => $supplierId,
            'workspace_id' => $entries->first()->workspace_id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'credits' => $calculation['credits'],
            'debits' => $calculation['debits'],
            'net_payable' => $calculation['net_payable'],
            'audit_snapshot' => $auditSnapshot,
            // Enhanced breakdown fields
            'gross_amount' => $calculation['gross_amount'] ?? $calculation['net_payable'],
            'diesel_deduction' => $calculation['diesel_deduction'] ?? 0,
            'calculation_method' => $calculation['calculation_method'] ?? 'legacy',
            'billing_breakdown' => $calculation['billing_breakdown'] ?? null,
            'diesel_breakdown' => $calculation['diesel_breakdown'] ?? null,
        ];
    }

    /**
     * INVARIANT 1: Period overlap validation
     * CRITICAL: Only locked periods should block, not drafts or rejected
     */
    private function validatePeriodOverlap(int $machineryId, string $periodStart, string $periodEnd): void
    {
        $overlappingPeriod = MachineryPaymentPeriod::where('machinery_id', $machineryId)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->where('start_date', '<=', $periodEnd)
                  ->where('end_date', '>=', $periodStart);
            })
            ->where('is_locked', true) // Only locked periods block
            ->first();
        
        if ($overlappingPeriod) {
            $this->auditLogger->logPaymentBlocked('machinery', 'period_overlap', [
                'machinery_id' => $machineryId,
                'new_period' => "{$periodStart} to {$periodEnd}",
                'existing_period' => "{$overlappingPeriod->start_date} to {$overlappingPeriod->end_date}",
            ]);
            
            throw new \RuntimeException('Payment period overlaps with existing locked period');
        }
    }
    
    /**
     * INVARIANT 2: Active request overlap validation
     * CRITICAL: Exclude rejected and paid only
     */
    private function validateActiveRequestOverlap(int $machineryId, string $periodStart, string $periodEnd): void
    {
        $overlappingRequest = MachineryPaymentRequest::where('machinery_id', $machineryId)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->where('period_start', '<=', $periodEnd)
                  ->where('period_end', '>=', $periodStart);
            })
            ->whereNotIn('status', ['rejected', 'paid'])
            ->first();
        
        if ($overlappingRequest) {
            $this->auditLogger->logPaymentBlocked('machinery', 'active_request_overlap', [
                'machinery_id' => $machineryId,
                'new_period' => "{$periodStart} to {$periodEnd}",
                'existing_request_id' => $overlappingRequest->id,
                'existing_status' => $overlappingRequest->status,
            ]);
            
            throw new \RuntimeException('Active payment request already exists for this period');
        }
    }
    
    /**
     * INVARIANT 3: Lock ledger entries
     * CRITICAL: Lock the actual rows being selected to prevent race condition
     * CRITICAL: Consistent ordering to prevent deadlocks
     */
    private function lockLedgerEntries(int $machineryId, string $periodStart, string $periodEnd)
    {
        // Debug logging - Step 1: Check all entries in period
        $allEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get();
        
        Log::debug('MachineryPaymentRequest - All entries in period', [
            'machinery_id' => $machineryId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'all_entries_count' => $allEntries->count(),
            'all_entries' => $allEntries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'amount' => $e->amount,
                'entry_direction' => $e->entry_direction,
                'entry_type' => $e->entry_type,
                'is_reversal' => $e->is_reversal,
                'payment_request_id' => $e->payment_request_id,
            ])->toArray(),
        ]);
        
        // Debug logging - Step 2: Check reversal filter
        $nonReversalEntries = $allEntries->where('is_reversal', false);
        $reversalEntries = $allEntries->where('is_reversal', true);
        
        Log::debug('MachineryPaymentRequest - After reversal filter', [
            'non_reversal_count' => $nonReversalEntries->count(),
            'reversal_count' => $reversalEntries->count(),
            'reversal_entries' => $reversalEntries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'amount' => $e->amount,
                'entry_direction' => $e->entry_direction,
                'entry_type' => $e->entry_type,
            ])->toArray(),
        ]);
        
        // Debug logging - Step 3: Check payment_request_id filter
        $unpaidEntries = $nonReversalEntries->whereNull('payment_request_id');
        $alreadyPaidEntries = $nonReversalEntries->whereNotNull('payment_request_id');
        
        Log::debug('MachineryPaymentRequest - After payment_request_id filter', [
            'unpaid_count' => $unpaidEntries->count(),
            'already_paid_count' => $alreadyPaidEntries->count(),
            'already_paid_entries' => $alreadyPaidEntries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'amount' => $e->amount,
                'entry_direction' => $e->entry_direction,
                'entry_type' => $e->entry_type,
                'payment_request_id' => $e->payment_request_id,
            ])->toArray(),
        ]);
        
        // Debug logging - Step 4: Check zero-amount filter
        $nonZeroAmountEntries = $unpaidEntries->where('amount', '>', 0);
        $zeroAmountEntries = $unpaidEntries->where('amount', '<=', 0);
        
        Log::debug('MachineryPaymentRequest - After zero-amount filter', [
            'non_zero_count' => $nonZeroAmountEntries->count(),
            'zero_amount_count' => $zeroAmountEntries->count(),
            'zero_amount_entries' => $zeroAmountEntries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'amount' => $e->amount,
                'entry_direction' => $e->entry_direction,
                'entry_type' => $e->entry_type,
            ])->toArray(),
        ]);
        
        // Execute the actual query with lockForUpdate
        $lockedEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_reversal', false)
            ->whereNull('payment_request_id') // Only unpaid entries
            ->where('amount', '>', 0) // Exclude zero-amount entries (they contribute nothing to payments)
            ->orderBy('date') // Consistent ordering prevents deadlocks
            ->orderBy('id')
            ->lockForUpdate() // Lock actual rows, not just period logic
            ->get();
        
        Log::debug('MachineryPaymentRequest - Final locked entries', [
            'locked_count' => $lockedEntries->count(),
            'locked_entries' => $lockedEntries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'amount' => $e->amount,
                'entry_direction' => $e->entry_direction,
                'entry_type' => $e->entry_type,
            ])->toArray(),
            'sql' => MachineryLedger::where('machinery_id', $machineryId)
                ->whereBetween('date', [$periodStart, $periodEnd])
                ->where('is_reversal', false)
                ->whereNull('payment_request_id')
                ->orderBy('date')
                ->orderBy('id')
                ->toSql(),
            'bindings' => [$machineryId, $periodStart, $periodEnd],
        ]);
        
        return $lockedEntries;
    }
    
    /**
     * INVARIANT 4: Financial correctness with enhanced calculation logic
     * 
     * @param $entries Collection of ledger entries
     * @param Machinery $machinery The machinery record
     * @param Carbon $from Period start date
     * @param Carbon $to Period end date
     * @return array Enhanced calculation results with breakdown
     */
    /**
     * INVARIANT 4: Financial correctness with enhanced calculation logic
     *
     * CALCULATION LOGIC:
     * - credits: Sum of ledger entries with entry_direction = 'credit' (work charges/earnings)
     * - debits: Sum of ledger entries with entry_direction = 'debit' (advances, deductions)
     * - net_payable: credits - debits (outstanding balance to supplier)
     *
     * For rental machinery:
     *   - Positive net_payable = Amount owed TO supplier
     *   - Negative net_payable = Amount supplier OWES to company (overpaid)
     *
     * @param $entries Collection of ledger entries
     * @param Machinery $machinery The machinery record
     * @param Carbon $from Period start date
     * @param Carbon $to Period end date
     * @return array Enhanced calculation results with breakdown
     */
    private function calculatePayable($entries, Machinery $machinery, Carbon $from, Carbon $to): array
    {
        // Get DPRs for the period (for informational purposes)
        $dprs = \App\Models\DailyProgressReport::where('machinery_id', $machinery->id)
            ->whereBetween('date', [$from, $to])
            ->get();

        // Calculate billing using centralized service (for reference)
        $billingResult = \App\Services\MachineryBillingCalculatorService::calculate($machinery, $dprs, $from, $to);

        // Calculate diesel deduction
        $dieselResult = \App\Services\MachineryDieselAdjustmentService::calculateDieselDeduction($machinery, $from, $to);

        $grossAmount = $billingResult['gross_amount'];
        $dieselDeduction = $dieselResult['applicable_for_deduction'] ? $dieselResult['total_cost'] : 0;

        // Calculate credits and debits from ledger entries
        // CRITICAL: Net Payable formula = Credits - Debits
        // - Credits: Work done by supplier (amounts owed to supplier)
        // - Debits: Advances, penalties, deductions (amounts already paid/adjusted)
        $credits = $entries->where('entry_direction', 'credit')->sum('amount');
        $debits = $entries->where('entry_direction', 'debit')
            ->filter(function ($entry) use ($machinery) {
                // Always include non-diesel debits (maintenance, advances)
                if ($entry->entry_type !== 'diesel') {
                    return true;
                }
                // For diesel, only include if company pays
                return $machinery->diesel_by_company;
            })
            ->sum('amount');

        // Net Payable = Credits - Debits
        // This represents the outstanding balance:
        // - Positive: Supplier is owed money
        // - Negative: Supplier has been overpaid (credit balance)
        $netPayable = $credits - $debits;

        return [
            // Enhanced breakdown fields
            'gross_amount' => $grossAmount,
            'diesel_deduction' => $dieselDeduction,
            'net_payable' => $netPayable,
            'billing_breakdown' => $billingResult,
            'diesel_breakdown' => $dieselResult,
            'calculation_method' => $billingResult['calculation_type'],

            // Ledger-based calculation fields (AUTHORITATIVE)
            'credits' => $credits,
            'debits' => $debits,

            // Audit visibility fields
            'diesel_deduction_applied' => $dieselResult['applicable_for_deduction'],
            'diesel_responsibility' => $machinery->diesel_by_company ? 'company' : 'supplier',
            'calculation_formula' => 'credits - debits',
            'entry_count' => $entries->count(),
        ];
    }
    
    /**
     * INVARIANT 5: Audit reproducibility
     * CRITICAL: Include entries_hash for tamper detection
     * CRITICAL: Hash ONLY immutable financial fields (not derived fields)
     */
    private function buildAuditSnapshot($entries, array $calculation): array
    {
        // Hash ONLY immutable financial fields
        // DO NOT include: running_balance (derived), updated_at, evolving metadata
        // CRITICAL: Sort before hashing for deterministic result (Scenario 33, 34)
        $sortedEntries = $entries->sortBy(['date', 'id']);
        $entriesHash = hash('sha256', json_encode($sortedEntries->map(fn($e) => [
            'id' => $e->id,
            'date' => $e->date,
            'amount' => $e->amount,
            'entry_direction' => $e->entry_direction,
            'entry_type' => $e->entry_type,
        ])->toArray()));
        
        return [
            'ledger_entry_ids' => $entries->pluck('id')->toArray(),
            'entries_hash' => $entriesHash, // Tamper detection
            'calculation_version' => 'v1',
            'calculation_timestamp' => now()->toDateTimeString(),
            'credits' => $calculation['credits'],
            'debits' => $calculation['debits'],
            'net_payable' => $calculation['net_payable'],
            'entry_count' => $entries->count(),
            'entry_details' => $entries->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->date,
                'direction' => $e->entry_direction,
                'type' => $e->entry_type,
                'amount' => $e->amount,
            ])->toArray(),
        ];
    }
    
    /**
     * Submit payment request for review
     */
    public function submit(int $paymentRequestId, int $userId): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        $from = MachineryPaymentStatus::from($request->status);
        $to = MachineryPaymentStatus::SUBMITTED;
        
        if (!$from->canTransitionTo($to)) {
            throw new \RuntimeException("Cannot submit request in status: {$from->value}");
        }
        
        $request->update([
            'status' => $to->value,
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);
        
        $this->auditLogger->logStateTransition('machinery', $from->value, $to->value, [
            'payment_request_id' => $request->id,
        ]);
    }
    /**
     * Approve payment request → LOCK period + link ledger entries
     * CRITICAL: Option A - link on approval
     */
    public function approve(int $paymentRequestId, int $userId): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        $from = MachineryPaymentStatus::from($request->status);
        $to = MachineryPaymentStatus::APPROVED;
        
        if (!$from->canTransitionTo($to)) {
            throw new \RuntimeException("Cannot approve request in status: {$from->value}");
        }
        
        // HARD GUARD: Cannot approve non-positive payable requests (Scenario 7, 8)
        // This is a financial safety invariant - even if status somehow allows it
        // Zero or negative net payable means nothing to pay - approval is invalid
        if ($request->net_payable <= 0) {
            $this->auditLogger->logPaymentBlocked('machinery', 'non_positive_payable_approval_blocked', [
                'payment_request_id' => $request->id,
                'net_payable' => $request->net_payable,
                'status' => $request->status,
            ]);
            throw new \RuntimeException(
                "Cannot approve payment request with non-positive net payable. " .
                "Net: {$request->net_payable}. Positive balance required for approval."
            );
        }
        
        // CRITICAL: Recalculate at approval (STRICT model - Option A)
        // If mismatch → block approval (Scenario 32: Ledger Mutation Between Verify → Approve)
        $this->reverifyCalculation($request);
        
        $this->withDeadlockRetry(function () use ($request, $userId, $from, $to) {
            $this->safeTransaction(function () use ($request, $userId, $from, $to) {
                // Lock period
                $this->lockPeriod($request, $userId);
                
                // Create separate ledger entries for work charges and diesel recovery
                $this->createSeparateLedgerEntries($request, $userId);
                
                // Get the created ledger entries for audit logging
                $ledgerEntries = MachineryLedger::where('payment_request_id', $request->id)
                    ->where('is_reversal', false)
                    ->get();
                $ledgerEntryIds = $ledgerEntries->pluck('id')->toArray();
                $linkedCount = count($ledgerEntryIds);
                
                // Validate ledger balance integrity after creating entries
                \App\Services\LedgerBalancingValidationService::validateForApproval($request);
                
                // Mark DPRs and ledger entries as billed
                \App\Services\MachineryBillingProtectionService::markDprsAsBilled($request);
                \App\Services\MachineryBillingProtectionService::markLedgerEntriesAsBilled($request);
                
                // Apply financial snapshot versioning
                \App\Services\FinancialSnapshotVersioningService::applyVersioningToPaymentRequest($request);
                \App\Services\FinancialSnapshotVersioningService::applyVersioningToLedgerEntries($request);
                
                // Update status
                $request->update([
                    'status' => $to->value,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]);
                
                // CRITICAL: Log approval event with full audit backbone
                $this->auditLogger->logPaymentCreated('machinery_approval', [
                    'payment_request_id' => $request->id,
                    'ledger_entry_ids' => $ledgerEntryIds,
                    'entries_hash' => $request->audit_snapshot['entries_hash'] ?? null,
                    'net_payable' => $request->net_payable,
                    'calculation_version' => $request->audit_snapshot['calculation_version'] ?? null,
                    'calculation_timestamp' => $request->audit_snapshot['calculation_timestamp'] ?? null,
                    'credits' => $request->credits,
                    'debits' => $request->debits,
                    'ledger_entries_linked' => $linkedCount,
                    'approved_by' => $userId,
                    'approved_at' => now()->toDateTimeString(),
                ]);
                
                $this->auditLogger->logStateTransition('machinery', $from->value, $to->value, [
                    'payment_request_id' => $request->id,
                    'ledger_entries_linked' => $linkedCount,
                ]);
            });
        });
    }
    
    /**
     * Lock period (source of truth)
     */
    private function lockPeriod(MachineryPaymentRequest $request, int $userId): void
    {
        MachineryPaymentPeriod::create([
            'machinery_id' => $request->machinery_id,
            'workspace_id' => $request->workspace_id,
            'start_date' => $request->period_start,
            'end_date' => $request->period_end,
            'is_locked' => true,
            'locked_at' => now(),
            'payment_request_id' => $request->id,
            'notes' => "Locked by payment request #{$request->id}",
            'created_by' => $userId,
        ]);
    }
    
    /**
     * Re-verify calculation hasn't changed
     * CRITICAL: STRICT model - recalculate at approval, block if mismatch
     */
    private function reverifyCalculation(MachineryPaymentRequest $request): void
    {
        $ledgerEntryIds = $request->audit_snapshot['ledger_entry_ids'] ?? [];
        
        $entries = MachineryLedger::whereIn('id', $ledgerEntryIds)
            ->where('is_reversal', false)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        
        // Load machinery to ensure consistent diesel filtering
        $machinery = Machinery::find($request->machinery_id);
        
        $credits = $entries->where('entry_direction', 'credit')->sum('amount');
        
        // Filter debits: exclude diesel if supplier pays (must match original calculation)
        $debits = $entries->where('entry_direction', 'debit')
            ->filter(function ($entry) use ($machinery) {
                // Always include non-diesel debits (maintenance, advances)
                if ($entry->entry_type !== 'diesel') {
                    return true;
                }
                // For diesel, only include if company pays
                return DieselResponsibilityService::companyPaysDiesel($machinery);
            })
            ->sum('amount');
        
        $netPayable = $credits - $debits;
        
        if (abs($netPayable - $request->net_payable) > 0.01) {
            $this->auditLogger->logPaymentBlocked('machinery', 'calculation_mismatch', [
                'payment_request_id' => $request->id,
                'original_net_payable' => $request->net_payable,
                'current_net_payable' => $netPayable,
                'diesel_responsibility' => $machinery->diesel_by_company ? 'company' : 'supplier',
            ]);
            
            throw new \RuntimeException(
                'Ledger calculation has changed since payment request was created. ' .
                "Original: {$request->net_payable}, Current: {$netPayable}. Approval blocked."
            );
        }
    }
    
    /**
     * Lock payment request (after approval)
     */
    public function lock(int $paymentRequestId, int $userId): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        $from = MachineryPaymentStatus::from($request->status);
        $to = MachineryPaymentStatus::LOCKED;
        
        if (!$from->canTransitionTo($to)) {
            throw new \RuntimeException("Cannot lock request in status: {$from->value}");
        }
        
        $request->update([
            'status' => $to->value,
            'locked_by' => $userId,
            'locked_at' => now(),
        ]);
        
        $this->auditLogger->logStateTransition('machinery', $from->value, $to->value, [
            'payment_request_id' => $request->id,
        ]);
    }
    
    /**
     * Mark payment request as paid
     */
    public function markAsPaid(int $paymentRequestId, int $userId): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        $from = MachineryPaymentStatus::from($request->status);
        $to = MachineryPaymentStatus::PAID;
        
        if (!$from->canTransitionTo($to)) {
            throw new \RuntimeException("Cannot mark as paid in status: {$from->value}");
        }
        
        $this->withDeadlockRetry(function () use ($request, $userId, $from, $to) {
            $this->safeTransaction(function () use ($request, $userId, $from, $to) {
                // Create payment credit entry in machinery ledger
                $this->createPaymentCreditEntry($request, $userId);
                
                // Update status
                $request->update([
                    'status' => $to->value,
                    'paid_by' => $userId,
                    'paid_at' => now(),
                ]);
                
                $this->auditLogger->logStateTransition('machinery', $from->value, $to->value, [
                    'payment_request_id' => $request->id,
                    'payment_amount' => $request->net_payable,
                ]);
            });
        });
    }
    
    /**
     * Create payment credit entry in machinery ledger
     */
    private function createPaymentCreditEntry(MachineryPaymentRequest $request, int $userId): void
    {
        // Check if payment credit entry already exists for this payment request
        $existingEntry = MachineryLedger::where('reference_type', 'MachineryPaymentRequest')
            ->where('reference_id', $request->id)
            ->where('entry_type', 'payment_credit')
            ->first();
            
        if ($existingEntry) {
            Log::info('Payment credit entry already exists', [
                'payment_request_id' => $request->id,
                'existing_entry_id' => $existingEntry->id,
            ]);
            return;
        }
        
        // Create the payment credit entry
        $machinery = Machinery::findOrFail($request->machinery_id);
        
        // Calculate running balance with row lock to prevent race conditions
        $lastBalance = MachineryLedger::where('machinery_id', $request->machinery_id)
            ->where('is_reversal', false)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->value('running_balance') ?? 0;
        
        $runningBalance = $lastBalance - $request->net_payable;
        
        MachineryLedger::create([
            'machinery_id' => $request->machinery_id,
            'workspace_id' => $request->workspace_id,
            'entry_direction' => 'debit',
            'entry_type' => 'payment_debit',
            'ledger_type' => $machinery->owned_by === 'owned' ? 'internal_cost' : 'payable',
            'cost_category' => 'payment',
            'reference_type' => 'MachineryPaymentRequest',
            'reference_id' => $request->id,
            'payment_request_id' => $request->id,
            'amount' => $request->net_payable,
            'running_balance' => $runningBalance,
            'date' => now()->toDateString(),
            'description' => "Payment #{$request->id} - {$machinery->name}",
            'is_reversal' => false,
            'metadata' => [
                'payment_request_id' => $request->id,
                'paid_by' => $userId,
                'paid_at' => now()->toDateTimeString(),
            ],
        ]);
        
        Log::info('Payment credit entry created', [
            'payment_request_id' => $request->id,
            'machinery_id' => $request->machinery_id,
            'amount' => $request->net_payable,
            'running_balance' => $runningBalance,
            'created_by' => $userId,
        ]);
    }
    
    /**
     * Reject payment request
     * CRITICAL: Reverse ledger entries (not unlink) to maintain financial integrity
     */
    public function reject(int $paymentRequestId, int $userId, ?string $reason = null): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        $from = MachineryPaymentStatus::from($request->status);
        $to = MachineryPaymentStatus::REJECTED;
        
        if (!$from->canTransitionTo($to)) {
            throw new \RuntimeException("Cannot reject request in status: {$from->value}");
        }
        
        $this->withDeadlockRetry(function () use ($request, $userId, $from, $to, $reason) {
            $this->safeTransaction(function () use ($request, $userId, $from, $to, $reason) {
                // Reverse all ledger entries linked to this payment request
                $ledgerEntryIds = $request->audit_snapshot['ledger_entry_ids'] ?? [];
                $reversedCount = 0;
                
                foreach ($ledgerEntryIds as $ledgerEntryId) {
                    try {
                        // Use LedgerService to create reversal entry
                        $reversalEntry = \App\Domain\Machinery\Services\MachineryLedgerService::reverseEntry(
                            $ledgerEntryId,
                            "Payment request #{$request->id} rejected: " . ($reason ?? 'No reason provided')
                        );
                        $reversedCount++;
                    } catch (\Exception $e) {
                        Log::warning('Failed to reverse ledger entry', [
                            'ledger_entry_id' => $ledgerEntryId,
                            'payment_request_id' => $request->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                // Unlink ledger entries from this payment request (after reversal)
                MachineryLedger::whereIn('id', $ledgerEntryIds)
                    ->where('payment_request_id', $request->id)
                    ->update(['payment_request_id' => null]);
                
                // Update status
                $request->update([
                    'status' => $to->value,
                    'remarks' => $reason,
                ]);
                
                $this->auditLogger->logStateTransition('machinery', $from->value, $to->value, [
                    'payment_request_id' => $request->id,
                    'reason' => $reason,
                    'ledger_entries_reversed' => $reversedCount,
                ]);
            });
        });
    }
    
    /**
     * ADMIN: Force reject after approval (emergency override)
     * CRITICAL: Admin-only operation with audit logging
     */
    public function forceReject(int $paymentRequestId, int $userId, string $overrideReason): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        $from = MachineryPaymentStatus::from($request->status);
        $to = MachineryPaymentStatus::REJECTED;
        
        // CRITICAL: Log admin override
        $this->auditLogger->logPaymentBlocked('machinery_admin_override', 'force_reject', [
            'payment_request_id' => $request->id,
            'original_status' => $from->value,
            'new_status' => $to->value,
            'override_reason' => $overrideReason,
            'admin_user_id' => $userId,
        ]);
        
        $request->update([
            'status' => $to->value,
            'remarks' => "ADMIN OVERRIDE: {$overrideReason}",
        ]);
        
        $this->auditLogger->logStateTransition('machinery', $from->value, $to->value, [
            'payment_request_id' => $request->id,
            'admin_override' => true,
            'override_reason' => $overrideReason,
        ]);
    }
    
    /**
     * ADMIN: Force unlock period (emergency override)
     * CRITICAL: Admin-only operation with audit logging
     */
    public function forceUnlockPeriod(int $paymentRequestId, int $userId, string $overrideReason): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        // Unlock the period
        $period = MachineryPaymentPeriod::where('payment_request_id', $paymentRequestId)->first();
        
        if ($period) {
            $this->auditLogger->logPaymentBlocked('machinery_admin_override', 'force_unlock_period', [
                'payment_request_id' => $paymentRequestId,
                'period_id' => $period->id,
                'override_reason' => $overrideReason,
                'admin_user_id' => $userId,
            ]);
            
            $period->update([
                'is_locked' => false,
                'locked_at' => null,
                'payment_request_id' => null,
                'notes' => "ADMIN UNLOCK: {$overrideReason}",
            ]);
        }
        
        // Unlink ledger entries
        MachineryLedger::where('payment_request_id', $paymentRequestId)
            ->update(['payment_request_id' => null]);
        
        $this->auditLogger->logStateTransition('machinery', $request->status, 'unlocked', [
            'payment_request_id' => $paymentRequestId,
            'admin_override' => true,
            'override_reason' => $overrideReason,
        ]);
    }
    
    /**
     * ADMIN: Add manual override note (does not change amount)
     * CRITICAL: For documentation only, no financial impact
     */
    public function addOverrideNote(int $paymentRequestId, int $userId, string $note): void
    {
        $request = MachineryPaymentRequest::findOrFail($paymentRequestId);
        
        $currentRemarks = $request->remarks ?? '';
        $newRemarks = empty($currentRemarks) ? $note : $currentRemarks . "\n\nADMIN NOTE: " . $note;
        
        $request->update([
            'remarks' => $newRemarks,
        ]);
        
        $this->auditLogger->logPaymentCreated('machinery_admin_note', [
            'payment_request_id' => $paymentRequestId,
            'note' => $note,
            'admin_user_id' => $userId,
        ]);
    }
    
    /**
     * Create separate ledger entries for work charges and diesel recovery
     */
    private function createSeparateLedgerEntries(MachineryPaymentRequest $request, int $userId): void
    {
        $machinery = $request->machinery;
        
        // Clear any existing ledger entries for this payment request (from failed approval attempts)
        MachineryLedger::where('payment_request_id', $request->id)
            ->where('reference_type', 'MachineryPaymentRequest')
            ->where('reference_id', $request->id)
            ->delete();
        
        // Entry 1: Machinery Work Charges (Credit)
        // Use net_payable + diesel_deduction to ensure entries sum to net_payable
        $creditAmount = $request->net_payable + $request->diesel_deduction;
        if ($creditAmount > 0) {
            \App\Domain\Machinery\Services\MachineryLedgerService::createCredit([
                'machinery_id' => $request->machinery_id,
                'amount' => $creditAmount,
                'entry_type' => 'work_charges',
                'description' => "Machinery work charges - payment #{$request->id}",
                'reference_type' => 'MachineryPaymentRequest',
                'reference_id' => $request->id,
                'payment_request_id' => $request->id
            ]);
        }
        
        // Entry 2: Diesel Recovery (Debit) - if applicable
        if ($request->diesel_deduction > 0) {
            \App\Domain\Machinery\Services\MachineryLedgerService::createDebit([
                'machinery_id' => $request->machinery_id,
                'amount' => $request->diesel_deduction,
                'entry_type' => 'diesel_recovery',
                'description' => "Diesel recovery - payment #{$request->id}",
                'reference_type' => 'MachineryPaymentRequest',
                'reference_id' => $request->id,
                'payment_request_id' => $request->id
            ]);
        }
    }
}
