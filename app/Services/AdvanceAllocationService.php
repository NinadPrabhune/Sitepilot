<?php

namespace App\Services;

use App\Models\SupplierAdvance;
use App\Models\AdvanceUtilization;
use App\Models\AdvanceAuditLog;
use App\Models\PurchaseInvoice;
use App\Models\PaymentRequest;
use App\Services\FinancialPeriodService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceAllocationService
{
    /**
     * Allocate advance to invoice with PO-locked logic and atomic transaction boundary.
     * CRITICAL: Uses DB-level locking, derived amounts, and FIFO with tie-breaker.
     * FEATURE FLAG: Only runs if finance.po_locked_advance_enabled is true.
     */
    public function allocateToInvoice(int $invoiceId): array
    {
        // CRITICAL: Feature flag check
        if (!config('finance.po_locked_advance_enabled', false)) {
            Log::channel('finance')->warning('PO-locked advance allocation skipped - feature flag disabled', [
                'invoice_id' => $invoiceId,
            ]);
            return [
                'success' => true,
                'message' => 'Feature flag disabled - using legacy allocation',
                'allocated_amount' => 0,
                'allocation_breakdown' => [],
            ];
        }

        return DB::transaction(function () use ($invoiceId) {
            $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($invoiceId);

            // CRITICAL: Direct GRN hard stop at service level
            if (empty($invoice->po_id)) {
                throw new \InvalidArgumentException(
                    'Direct GRN invoices cannot use advance allocation. ' .
                    'This invoice is not linked to a Purchase Order. ' .
                    'Direct GRN requires full payment without advance.'
                );
            }

            // CRITICAL: Validate invoice is not financially locked
            if ($invoice->is_locked) {
                throw new \InvalidArgumentException(
                    'Invoice is financially locked. No allocations allowed.'
                );
            }

            // CRITICAL: Validate financial period not closed (only if enabled)
            if (config('finance.financial_period_locking_enabled', false)) {
                $periodService = new FinancialPeriodService();
                $periodService->validatePeriodNotClosed(
                    Carbon::parse($invoice->invoice_date),
                    $invoice->workspace_id,
                    $invoice->site_id
                );
            }

            // Lock advances first
            $advances = SupplierAdvance::where('po_id', $invoice->po_id)
                ->where('supplier_id', $invoice->supplier_id)
                ->where('workspace_id', $invoice->workspace_id)
                ->where('site_id', $invoice->site_id)
                ->where('status', 'paid')
                ->where('locked_to_po', true)
                ->whereRaw('(amount - utilized_amount) > 0')  // Using derived remaining
                ->lockForUpdate()
                ->orderBy('advance_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit(50)  // CRITICAL: Batch limit
                ->get();

            if ($advances->count() >= 50) {
                Log::warning('Allocation batch limit reached', ['invoice_id' => $invoiceId]);
            }

            if ($advances->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No available advances for allocation',
                    'allocated_amount' => 0,
                    'allocation_breakdown' => [],
                ];
            }

            $invoiceBalance = $this->getInvoiceBalance($invoice);
            if ($invoiceBalance <= 0) {
                return [
                    'success' => true,
                    'message' => 'Invoice has no balance to allocate',
                    'allocated_amount' => 0,
                    'allocation_breakdown' => [],
                ];
            }

            $totalAllocated = 0;
            $allocationBreakdown = [];

            foreach ($advances as $advance) {
                if ($invoiceBalance <= 0) {
                    break;
                }

                // CRITICAL: Validate transaction flow ID match
                if ($advance->transaction_flow_id !== $invoice->transaction_flow_id) {
                    throw new \InvalidArgumentException(
                        'Transaction flow mismatch: Advance flow ID ' . $advance->transaction_flow_id .
                        ' does not match Invoice flow ID ' . $invoice->transaction_flow_id
                    );
                }

                // CRITICAL: Calculate available balance (derived, not stored)
                $availableBalance = $advance->amount - $advance->utilized_amount;

                if ($availableBalance <= 0) {
                    continue;
                }

                $toAllocate = min($availableBalance, $invoiceBalance);

                // Insert utilization records with status='applied' (direct allocation)
                $utilization = AdvanceUtilization::create([
                    'supplier_advance_id' => $advance->id,
                    'purchase_invoice_id' => $invoice->id,
                    'utilized_amount' => $toAllocate,
                    'status' => 'applied',
                    'applied_at' => now(),
                    'workspace_id' => $invoice->workspace_id,
                    'site_id' => $invoice->site_id,
                ]);

                $allocationBreakdown[] = [
                    'advance_number' => $advance->advance_number,
                    'advance_id' => $advance->id,
                    'amount' => $toAllocate,
                ];

                $totalAllocated += $toAllocate;
                $invoiceBalance -= $toAllocate;
            }

            // CRITICAL: Update advance utilized_amount using SUM (idempotent)
            foreach ($advances as $advance) {
                $totalApplied = AdvanceUtilization::where('supplier_advance_id', $advance->id)
                    ->where('status', 'applied')
                    ->sum('utilized_amount');

                SupplierAdvance::where('id', $advance->id)->update([
                    'utilized_amount' => $totalApplied,  // Source of truth = utilization table
                ]);
            }

            // Validate invoice total
            $totalAllocatedCheck = AdvanceUtilization::where('purchase_invoice_id', $invoice->id)
                ->where('status', 'applied')
                ->sum('utilized_amount');

            if ($totalAllocatedCheck > $invoice->grand_total) {
                throw new \InvalidArgumentException('Allocation exceeds invoice total');
            }

            Log::channel('finance')->info('Advance allocated to invoice', [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $totalAllocated,
                'flow_id' => $invoice->transaction_flow_id,
                'workspace_id' => $invoice->workspace_id,
                'site_id' => $invoice->site_id,
                'user_id' => auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => 'Advance allocated successfully',
                'allocated_amount' => $totalAllocated,
                'allocation_breakdown' => $allocationBreakdown,
            ];
        });
    }

    /**
     * Reserve advance for payment request (prevents partial failures)
     * FEATURE FLAG: Only runs if finance.po_locked_advance_enabled is true.
     */
    public function reserveForPaymentRequest(int $invoiceId, int $paymentRequestId): array
    {
        // CRITICAL: Feature flag check
        if (!config('finance.po_locked_advance_enabled', false)) {
            return ['status' => 'skipped', 'payment_request_id' => $paymentRequestId];
        }

        return DB::transaction(function () use ($invoiceId, $paymentRequestId) {
            $invoice = PurchaseInvoice::lockForUpdate()->findOrFail($invoiceId);

            // CRITICAL: Direct GRN hard stop
            if (empty($invoice->po_id)) {
                throw new \InvalidArgumentException(
                    'Direct GRN invoices cannot use advance allocation. ' .
                    'This invoice is not linked to a Purchase Order.'
                );
            }

            // Lock advances
            $advances = SupplierAdvance::where('po_id', $invoice->po_id)
                ->where('supplier_id', $invoice->supplier_id)
                ->where('workspace_id', $invoice->workspace_id)
                ->where('site_id', $invoice->site_id)
                ->where('status', 'paid')
                ->where('locked_to_po', true)
                ->whereRaw('(amount - utilized_amount) > 0')
                ->lockForUpdate()
                ->orderBy('advance_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit(50)
                ->get();

            $remainingToAllocate = $this->getInvoiceBalance($invoice);

            // Create reservation records (status='reserved')
            foreach ($advances as $advance) {
                $available = $advance->amount - $advance->utilized_amount;
                $utilizedAmount = min($available, $remainingToAllocate);

                if ($utilizedAmount > 0) {
                    AdvanceUtilization::create([
                        'supplier_advance_id' => $advance->id,
                        'purchase_invoice_id' => $invoice->id,
                        'utilized_amount' => $utilizedAmount,
                        'status' => 'reserved',
                        'reserved_at' => now(),
                        'workspace_id' => $invoice->workspace_id,
                        'site_id' => $invoice->site_id,
                    ]);

                    $remainingToAllocate -= $utilizedAmount;
                }
            }

            Log::channel('finance')->info('Advance Reserved', [
                'invoice_id' => $invoiceId,
                'payment_request_id' => $paymentRequestId,
                'amount' => $this->getInvoiceBalance($invoice) - $remainingToAllocate,
                'flow_id' => $invoice->transaction_flow_id,
            ]);

            return ['status' => 'reserved', 'payment_request_id' => $paymentRequestId];
        });
    }

    /**
     * Apply reservation on payment success
     * FEATURE FLAG: Only runs if finance.po_locked_advance_enabled is true.
     */
    public function applyReservation(int $paymentRequestId): void
    {
        // CRITICAL: Feature flag check
        if (!config('finance.po_locked_advance_enabled', false)) {
            return;
        }

        DB::transaction(function () use ($paymentRequestId) {
            $paymentRequest = PaymentRequest::findOrFail($paymentRequestId);

            // Change status from reserved to applied
            AdvanceUtilization::where('purchase_invoice_id', $paymentRequest->purchase_invoice_id)
                ->where('status', 'reserved')
                ->update([
                    'status' => 'applied',
                    'applied_at' => now(),
                ]);

            // Recalculate utilized_amount from applied records only
            $invoice = PurchaseInvoice::find($paymentRequest->purchase_invoice_id);
            $utilizationIds = AdvanceUtilization::where('purchase_invoice_id', $invoice->id)
                ->pluck('supplier_advance_id');

            foreach ($utilizationIds as $advanceId) {
                $totalApplied = AdvanceUtilization::where('supplier_advance_id', $advanceId)
                    ->where('status', 'applied')
                    ->sum('utilized_amount');

                SupplierAdvance::where('id', $advanceId)->update([
                    'utilized_amount' => $totalApplied,
                ]);
            }

            Log::channel('finance')->info('Advance Applied', [
                'payment_request_id' => $paymentRequestId,
                'flow_id' => $paymentRequest->transaction_flow_id,
            ]);
        });
    }

    /**
     * Release reservation on payment failure
     * FEATURE FLAG: Only runs if finance.po_locked_advance_enabled is true.
     */
    public function releaseReservation(int $paymentRequestId): void
    {
        // CRITICAL: Feature flag check
        if (!config('finance.po_locked_advance_enabled', false)) {
            return;
        }

        DB::transaction(function () use ($paymentRequestId) {
            $paymentRequest = PaymentRequest::findOrFail($paymentRequestId);

            // Delete reservation records
            AdvanceUtilization::where('purchase_invoice_id', $paymentRequest->purchase_invoice_id)
                ->where('status', 'reserved')
                ->delete();

            // Recalculate utilized_amount (only applied records)
            $invoice = PurchaseInvoice::find($paymentRequest->purchase_invoice_id);
            $utilizationIds = AdvanceUtilization::where('purchase_invoice_id', $invoice->id)
                ->pluck('supplier_advance_id');

            foreach ($utilizationIds as $advanceId) {
                $totalApplied = AdvanceUtilization::where('supplier_advance_id', $advanceId)
                    ->where('status', 'applied')
                    ->sum('utilized_amount');

                SupplierAdvance::where('id', $advanceId)->update([
                    'utilized_amount' => $totalApplied,
                ]);
            }
        });
    }

    /**
     * Reverse allocation for invoice cancellation
     */
    public function reverseAllocation(int $utilizationId, string $reason): void
    {
        DB::transaction(function () use ($utilizationId, $reason) {
            $utilization = AdvanceUtilization::with('advance')->findOrFail($utilizationId);

            // Mark as reversed
            $utilization->update([
                'status' => 'reversed',
                'reversed_at' => now(),
            ]);

            // Recalculate utilized_amount from applied records only
            $totalApplied = AdvanceUtilization::where('supplier_advance_id', $utilization->supplier_advance_id)
                ->where('status', 'applied')
                ->sum('utilized_amount');

            SupplierAdvance::where('id', $utilization->supplier_advance_id)->update([
                'utilized_amount' => $totalApplied,
            ]);

            // Add audit log
            AdvanceAuditLog::log(
                $utilization->advance,
                AdvanceAuditLog::ACTION_REVERSED,
                ['status' => 'applied'],
                ['status' => 'reversed'],
                $utilization->utilized_amount,
                auth()->id()
            );

            // Add ledger reversal entry
            LedgerDoubleEntryService::createReversalEntry($utilization, $reason);

            Log::channel('finance')->info('Advance Reversed', [
                'utilization_id' => $utilizationId,
                'reason' => $reason,
                'amount' => $utilization->utilized_amount,
                'flow_id' => $utilization->transaction_flow_id,
            ]);
        });
    }

    /**
     * Reverse all allocations for an invoice (invoice cancellation)
     */
    public function reverseAllAllocationsForInvoice(int $invoiceId, string $reason): void
    {
        DB::transaction(function () use ($invoiceId, $reason) {
            $utilizations = AdvanceUtilization::where('purchase_invoice_id', $invoiceId)
                ->where('status', 'applied')
                ->get();

            foreach ($utilizations as $utilization) {
                $this->reverseAllocation($utilization->id, $reason);
            }
        });
    }

    /**
     * Calculate available balance for a specific advance.
     * CRITICAL: Never trust stored values - always calculate.
     *
     * @param int $advanceId
     * @return float
     */
    public function calculateAvailableForAllocation(int $advanceId): float
    {
        $advance = SupplierAdvance::findOrFail($advanceId);
        return $advance->amount - $advance->utilized_amount;  // Derived, not stored
    }

    /**
     * Lock all supplier advances during allocation.
     * 
     * @param int $supplierId
     * @return void
     */
    public function lockAdvancesForAllocation(int $supplierId): void
    {
        SupplierAdvance::forSupplier($supplierId)
            ->paid()
            ->lockForUpdate()
            ->get();
    }

    /**
     * Unlock advances after allocation.
     * 
     * @param int $supplierId
     * @return void
     */
    public function unlockAdvancesAfterAllocation(int $supplierId): void
    {
        SupplierAdvance::forSupplier($supplierId)
            ->where('is_locked', true)
            ->update(['is_locked' => false]);
    }

    /**
     * Reserve advance for invoice in process.
     * 
     * @param int $advanceId
     * @param int $invoiceId
     * @param float $amount
     * @return bool
     */
    public function reserveAdvanceForInvoice(int $advanceId, int $invoiceId, float $amount): bool
    {
        return DB::transaction(function () use ($advanceId, $invoiceId, $amount) {
            $advance = SupplierAdvance::lockForUpdate()->findOrFail($advanceId);
            return $advance->reserve($amount, $invoiceId);
        });
    }

    /**
     * Release reservation for invoice.
     * 
     * @param int $advanceId
     * @param int $invoiceId
     * @return bool
     */
    public function unreserveAdvanceForInvoice(int $advanceId, int $invoiceId): bool
    {
        return DB::transaction(function () use ($advanceId, $invoiceId) {
            $advance = SupplierAdvance::lockForUpdate()->findOrFail($advanceId);
            return $advance->releaseReservation($advance->reserved_amount);
        });
    }

    /**
     * Release expired reservations for a supplier.
     * 
     * @param int $supplierId
     * @return int
     */
    public function releaseExpiredReservations(int $supplierId): int
    {
        $expiredAdvances = SupplierAdvance::forSupplier($supplierId)
            ->where('reservation_expires_at', '<', now())
            ->where('reserved_amount', '>', 0)
            ->get();

        $releasedCount = 0;

        foreach ($expiredAdvances as $advance) {
            if ($advance->releaseExpiredReservation()) {
                $releasedCount++;
                
                SupplierAdvanceAuditLog::log(
                    $advance,
                    SupplierAdvanceAuditLog::ACTION_UNRESERVATION,
                    ['reserved_amount' => $advance->reserved_amount],
                    ['reserved_amount' => 0],
                    $advance->reserved_amount,
                    null,
                    'Expired reservation released',
                    null
                );
            }
        }

        if ($releasedCount > 0) {
            Log::info('Expired reservations released', [
                'supplier_id' => $supplierId,
                'count' => $releasedCount,
            ]);
        }

        return $releasedCount;
    }

    /**
     * Rollback allocation if payment fails (atomic rollback).
     * 
     * @param int $invoiceId
     * @return bool
     */
    public function rollbackAllocation(int $invoiceId): bool
    {
        return DB::transaction(function () use ($invoiceId) {
            $utilizations = AdvanceUtilization::where('purchase_invoice_id', $invoiceId)
                ->lockForUpdate()
                ->get();

            if ($utilizations->isEmpty()) {
                return true;
            }

            $totalReleased = 0;

            foreach ($utilizations as $utilization) {
                $advance = $utilization->advance;
                $beforeState = $advance->toArray();

                // Reverse the allocation
                $advance->update([
                    'allocated_amount' => $advance->allocated_amount - $utilization->utilized_amount,
                    'utilized_amount' => $advance->utilized_amount - $utilization->utilized_amount,
                    'remaining_amount' => $advance->remaining_amount + $utilization->utilized_amount,
                ]);

                // Create audit log
                SupplierAdvanceAuditLog::log(
                    $advance,
                    SupplierAdvanceAuditLog::ACTION_ROLLBACK,
                    $beforeState,
                    $advance->fresh()->toArray(),
                    $utilization->utilized_amount,
                    $invoiceId,
                    'Allocation rolled back',
                    auth()->id()
                );

                // Delete utilization record
                $utilization->delete();

                $totalReleased += $utilization->utilized_amount;
            }

            // Unlock invoice
            PurchaseInvoice::where('id', $invoiceId)->update([
                'is_financially_locked' => false,
                'financially_locked_at' => null,
                'financially_locked_by' => null,
            ]);

            Log::info('Advance allocation rolled back', [
                'invoice_id' => $invoiceId,
                'total_released' => $totalReleased,
            ]);

            return true;
        });
    }

    /**
     * Check if advance has been allocated for an invoice.
     * 
     * @param int $invoiceId
     * @return bool
     */
    public function isAdvanceAllocatedForInvoice(int $invoiceId): bool
    {
        return AdvanceUtilization::getTotalUtilizedForInvoice($invoiceId) > 0;
    }

    /**
     * Calculate potential advance allocation for an invoice (dry-run).
     * Returns the amount that would be allocated without persisting any changes.
     * 
     * @param int $invoiceId
     * @return array
     */
    public function calculatePotentialAllocation(int $invoiceId): array
    {
        $invoice = PurchaseInvoice::with('supplier')->findOrFail($invoiceId);

        // Check if invoice is already financially locked
        if ($invoice->is_financially_locked) {
            return [
                'success' => false,
                'message' => 'Invoice is financially locked',
                'allocated_amount' => 0,
            ];
        }

        // Get available advances (without locking for dry-run)
        $advances = SupplierAdvance::forSupplier($invoice->supplier_id)
            ->paid()
            ->whereRaw('remaining_amount - reserved_amount - utilized_amount > 0')
            ->orderBy('advance_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($advances->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No available advances for allocation',
                'allocated_amount' => 0,
                'allocation_breakdown' => [],
            ];
        }

        $invoiceBalance = $this->getInvoiceBalance($invoice);
        if ($invoiceBalance <= 0) {
            return [
                'success' => true,
                'message' => 'Invoice has no balance to allocate',
                'allocated_amount' => 0,
                'allocation_breakdown' => [],
            ];
        }

        $totalAllocated = 0;
        $allocationBreakdown = [];

        foreach ($advances as $advance) {
            if ($invoiceBalance <= 0) {
                break;
            }

            $availableBalance = $advance->getAvailableBalanceAttribute();

            if ($availableBalance <= 0) {
                continue;
            }

            $toAllocate = min($availableBalance, $invoiceBalance);

            $allocationBreakdown[] = [
                'advance_number' => $advance->advance_number,
                'advance_id' => $advance->id,
                'amount' => $toAllocate,
            ];

            $totalAllocated += $toAllocate;
            $invoiceBalance -= $toAllocate;
        }

        return [
            'success' => true,
            'message' => 'Potential allocation calculated',
            'allocated_amount' => $totalAllocated,
            'allocation_breakdown' => $allocationBreakdown,
        ];
    }

    /**
     * Get invoice balance after deducting payments and advances.
     * 
     * @param PurchaseInvoice $invoice
     * @return float
     */
    private function getInvoiceBalance(PurchaseInvoice $invoice): float
    {
        $grandTotal = (float) $invoice->grand_total;
        $directPayments = $invoice->payments()->sum('amount');
        $advanceUtilized = AdvanceUtilization::getTotalUtilizedForInvoice($invoice->id);
        
        return max(0, $grandTotal - $directPayments - $advanceUtilized);
    }
}