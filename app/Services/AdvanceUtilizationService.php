<?php

namespace App\Services;

use App\Models\AdvanceUtilization;
use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;
use App\Models\SupplierAdvance;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceUtilizationService
{
    /**
     * Allocate PO advances for invoice payment with reservation layer and global PO lock.
     * 
     * This implements the ERP-safe utilization flow:
     * 1. Lock PO + Advances (global lock)
     * 2. Reserve utilization first with idempotency key
     * 3. Mark as applied after successful payment
     * 4. Handle failures by marking as failed
     * 
     * @param PurchaseInvoice $invoice
     * @param PaymentsModule $payment
     * @param string|null $idempotencyKey Client-generated UUID for idempotency
     * @return array
     * @throws \Exception
     */
    public function allocateForInvoicePayment(PurchaseInvoice $invoice, PaymentsModule $payment, ?string $idempotencyKey = null): array
    {
        if (!$invoice->po_id) {
            return ['success' => true, 'message' => 'Invoice not linked to PO, no advance utilization'];
        }

        return DB::transaction(function () use ($invoice, $payment, $idempotencyKey) {
            // Global PO lock + Advance lock (prevents approval + payment overlap)
            // Using SELECT ... FOR UPDATE (DB-level lock, not application-level)
            $po = PurchaseOrder::where('id', $invoice->po_id)->lockForUpdate()->first();
            if (!$po) {
                throw new \Exception('PO not found');
            }

            $advances = SupplierAdvance::where('po_id', $invoice->po_id)
                ->where('status', SupplierAdvance::STATUS_APPROVED)
                ->lockForUpdate()
                ->get();

            if ($advances->isEmpty()) {
                return ['success' => true, 'message' => 'No approved PO advances available'];
            }

            $paymentAmount = $payment->amount;
            $utilizationIds = [];
            $totalReserved = 0;

            foreach ($advances as $advance) {
                // Calculate available balance dynamically (amount - utilized_amount)
                $available = $advance->amount - $advance->utilized_amount;
                if ($available <= 0) continue;

                $deductAmount = min($available, $paymentAmount);

                // Step 1: Reserve amount first (reservation layer) with idempotency key
                $utilization = AdvanceUtilization::create([
                    'idempotency_key' => $idempotencyKey,
                    'supplier_advance_id' => $advance->id,
                    'purchase_invoice_id' => $invoice->id,
                    'payments_module_id' => $payment->id,
                    'utilized_amount' => $deductAmount,
                    'status' => AdvanceUtilization::STATUS_RESERVED,
                    'reserved_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                $utilizationIds[] = $utilization->id;
                $totalReserved += $deductAmount;
                $paymentAmount -= $deductAmount;

                if ($paymentAmount <= 0) break;
            }

            // Partial allocation handling: if payment > available advances
            // Allow partial allocation (advance + external payment) - this is ERP standard
            $partialAllocation = $paymentAmount > 0;

            // Step 2: Mark as applied after successful payment creation
            // This happens after the payment is confirmed to be successful
            // The calling controller should call applyReservedUtilizations() after payment success
            return [
                'success' => true,
                'message' => $partialAllocation 
                    ? 'Advance utilization partially reserved (advance + external payment required)' 
                    : 'Advance utilization reserved successfully',
                'utilization_ids' => $utilizationIds,
                'total_reserved' => $totalReserved,
                'payment_amount' => $payment->amount,
                'remaining_payment_needed' => max(0, $paymentAmount),
                'partial_allocation' => $partialAllocation,
            ];
        });
    }

    /**
     * Apply reserved utilizations after successful payment.
     * 
     * @param int $paymentId
     * @return void
     * @throws \Exception
     */
    public function applyReservedUtilizations(int $paymentId): void
    {
        DB::transaction(function () use ($paymentId) {
            $updated = AdvanceUtilization::where('payments_module_id', $paymentId)
                ->where('status', AdvanceUtilization::STATUS_RESERVED)
                ->update([
                    'status' => AdvanceUtilization::STATUS_APPLIED,
                    'applied_at' => now(),
                ]);

            Log::info('Advance utilizations applied', [
                'payment_id' => $paymentId,
                'count' => $updated,
            ]);
        });
    }

    /**
     * Mark reserved utilizations as failed on payment failure.
     * 
     * @param int $paymentId
     * @param string $reason
     * @return void
     * @throws \Exception
     */
    public function markUtilizationsAsFailed(int $paymentId, string $reason): void
    {
        DB::transaction(function () use ($paymentId, $reason) {
            $updated = AdvanceUtilization::where('payments_module_id', $paymentId)
                ->where('status', AdvanceUtilization::STATUS_RESERVED)
                ->update([
                    'status' => AdvanceUtilization::STATUS_REVERSED,
                    'reversed_at' => now(),
                ]);

            Log::info('Advance utilizations marked as failed', [
                'payment_id' => $paymentId,
                'count' => $updated,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Get total available advance balance for a PO.
     * 
     * CRITICAL: Calculate dynamically - SUM(approved advances) - SUM(applied utilizations) - SUM(reserved utilizations)
     * 
     * @param int $poId
     * @return float
     */
    public function getAvailableBalanceForPO(int $poId): float
    {
        $totalApproved = SupplierAdvance::where('po_id', $poId)
            ->where('status', SupplierAdvance::STATUS_APPROVED)
            ->sum('amount');

        $totalApplied = AdvanceUtilization::whereHas('advance', function($q) use ($poId) {
            $q->where('po_id', $poId);
        })->where('status', AdvanceUtilization::STATUS_APPLIED)
        ->sum('utilized_amount');

        $totalReserved = AdvanceUtilization::whereHas('advance', function($q) use ($poId) {
            $q->where('po_id', $poId);
        })->where('status', AdvanceUtilization::STATUS_RESERVED)
        ->sum('utilized_amount');

        return max(0, $totalApproved - $totalApplied - $totalReserved);
    }

    /**
     * Reconcile stale reserved entries.
     * 
     * This is a safety mechanism to handle the edge case where:
     * - Payment succeeds
     * - DB update fails before marking utilizations as applied
     * - Results in: Payment done, but utilizations still "reserved"
     * 
     * Should be run periodically (e.g., via cron job)
     * 
     * @param int $staleMinutes Minutes after which a reservation is considered stale (default: 5 minutes)
     * @return array
     */
    public function reconcileStaleReservedEntries(int $staleMinutes = 5): array
    {
        $cutoffTime = now()->subMinutes($staleMinutes);
        
        $staleEntries = DB::transaction(function () use ($cutoffTime) {
            return AdvanceUtilization::where('status', AdvanceUtilization::STATUS_RESERVED)
                ->where('reserved_at', '<', $cutoffTime)
                ->with(['payment', 'invoice'])
                ->get();
        });

        $repaired = 0;
        $failed = 0;

        foreach ($staleEntries as $utilization) {
            DB::transaction(function () use ($utilization, &$repaired, &$failed) {
                try {
                    // Check if payment exists and is successful
                    if ($utilization->payment && $utilization->payment->status === 'completed') {
                        // Payment succeeded but utilization not marked as applied - mark as applied
                        $utilization->update([
                            'status' => AdvanceUtilization::STATUS_APPLIED,
                            'applied_at' => now(),
                        ]);
                        $repaired++;
                        
                        Log::info('Reconciled stale reserved entry to applied', [
                            'utilization_id' => $utilization->id,
                            'payment_id' => $utilization->payment->id,
                            'invoice_id' => $utilization->invoice->id,
                        ]);
                    } else {
                        // Payment doesn't exist or failed - mark as failed
                        $utilization->update([
                            'status' => AdvanceUtilization::STATUS_REVERSED,
                            'reversed_at' => now(),
                        ]);
                        $failed++;
                        
                        Log::warning('Reconciled stale reserved entry to reversed (payment not found or failed)', [
                            'utilization_id' => $utilization->id,
                            'payment_id' => $utilization->payments_module_id,
                            'invoice_id' => $utilization->invoice->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to reconcile stale reserved entry', [
                        'utilization_id' => $utilization->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }

        return [
            'total_stale' => $staleEntries->count(),
            'repaired' => $repaired,
            'failed' => $failed,
        ];
    }
}
