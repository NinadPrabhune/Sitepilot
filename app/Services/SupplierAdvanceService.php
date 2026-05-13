<?php

namespace App\Services;

use App\Models\SupplierAdvance;
use App\Models\SupplierAdvanceAuditLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupplierAdvanceService
{
    /**
     * Create immutable ledger entry from PO advance payment request.
     * 
     * This is called when a PO advance payment request is approved.
     * Uses approved_amount (not requested_amount) as the ledger amount.
     * 
     * @param \App\Models\PaymentRequest $paymentRequest
     * @return SupplierAdvance
     * @throws \Exception
     */
    public function createFromPaymentRequest(\App\Models\PaymentRequest $paymentRequest): SupplierAdvance
    {
        if (!$paymentRequest->isPoAdvance()) {
            throw new \Exception('Payment request must be PO advance type');
        }

        if (!$paymentRequest->po_id) {
            throw new \Exception('PO advance request must have po_id');
        }

        $po = PurchaseOrder::with('supplier')->findOrFail($paymentRequest->po_id);

        // Generate advance number
        $advanceNumber = $this->generateAdvanceNumber();

        // Use approved_amount as ledger amount (not requested_amount)
        $ledgerAmount = $paymentRequest->approved_amount ?? $paymentRequest->requested_amount;

        // Determine status based on payment request status
        $status = ($paymentRequest->status === \App\Models\PaymentRequest::STATUS_APPROVED) 
            ? SupplierAdvance::STATUS_APPROVED 
            : SupplierAdvance::STATUS_PENDING;

        $advance = SupplierAdvance::create([
            'supplier_id' => $po->supplier_id,
            'po_id' => $po->id,
            'site_id' => $po->site_id,
            'workspace_id' => $po->workspace_id,
            'created_by' => $paymentRequest->requested_by,
            'advance_number' => $advanceNumber,
            'advance_date' => $paymentRequest->payment_date ?? now()->toDateString(),
            'source' => SupplierAdvance::SOURCE_PO,
            'amount' => $ledgerAmount,
            'remaining_amount' => $ledgerAmount, // Initial remaining amount
            'status' => $status,
            'approved_by' => $paymentRequest->approved_by,
            'approved_at' => $paymentRequest->approved_at,
            'remarks' => $paymentRequest->remarks,
        ]);

        // Create audit log
        SupplierAdvanceAuditLog::log(
            $advance,
            SupplierAdvanceAuditLog::ACTION_ALLOCATION,
            [],
            $advance->toArray(),
            $ledgerAmount,
            null,
            'Supplier advance created from payment request #' . $paymentRequest->id,
            $paymentRequest->requested_by
        );

        Log::info('Supplier advance created from payment request', [
            'advance_id' => $advance->id,
            'advance_number' => $advance->advance_number,
            'payment_request_id' => $paymentRequest->id,
            'po_id' => $po->id,
            'ledger_amount' => $ledgerAmount,
            'requested_amount' => $paymentRequest->requested_amount,
            'approved_amount' => $paymentRequest->approved_amount,
        ]);

        return $advance;
    }

    /**
     * Create a new advance request.
     * 
     * @param int $poId
     * @param float $amount
     * @param array $data
     * @return SupplierAdvance
     */
    public function createAdvance(int $poId, float $amount, array $data): SupplierAdvance
    {
        $po = PurchaseOrder::with('supplier')->findOrFail($poId);

        // Generate advance number
        $advanceNumber = $this->generateAdvanceNumber();

        $advance = SupplierAdvance::create([
            'supplier_id' => $po->supplier_id,
            'po_id' => $poId,
            'site_id' => $po->site_id,
            'workspace_id' => $po->workspace_id,
            'created_by' => auth()->id(),
            'advance_number' => $advanceNumber,
            'advance_date' => $data['advance_date'] ?? now()->toDateString(),
            'source' => $data['source'] ?? SupplierAdvance::SOURCE_PO,
            'amount' => $amount,
            'remaining_amount' => $amount,
            'status' => SupplierAdvance::STATUS_PENDING,
            'remarks' => $data['remarks'] ?? null,
        ]);

        // Create audit log
        SupplierAdvanceAuditLog::log(
            $advance,
            SupplierAdvanceAuditLog::ACTION_ALLOCATION,
            [],
            $advance->toArray(),
            $amount,
            null,
            'Advance request created',
            auth()->id()
        );

        Log::info('Advance request created', [
            'advance_id' => $advance->id,
            'advance_number' => $advance->advance_number,
            'amount' => $amount,
            'po_id' => $poId,
        ]);

        return $advance;
    }

    /**
     * Approve an advance request and trigger ledger entry.
     * 
     * @param int $advanceId
     * @param int $userId
     * @return SupplierAdvance
     */
    public function approveAdvance(int $advanceId, int $userId): SupplierAdvance
    {
        return DB::transaction(function () use ($advanceId, $userId) {
            $advance = SupplierAdvance::with('supplier')->lockForUpdate()->findOrFail($advanceId);

            if ($advance->status !== SupplierAdvance::STATUS_PENDING) {
                throw new \Exception('Advance can only be approved when in pending status');
            }

            $beforeState = $advance->toArray();

            $advance->update([
                'status' => SupplierAdvance::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // Create audit log
            SupplierAdvanceAuditLog::log(
                $advance,
                SupplierAdvanceAuditLog::ACTION_ALLOCATION,
                $beforeState,
                $advance->fresh()->toArray(),
                $advance->amount,
                null,
                'Advance approved',
                $userId
            );

            // Trigger ledger entry (will be implemented in LedgerHelper)
            // LedgerHelper::createAdvanceApprovalLedgerEntry($advance);

            Log::info('Advance approved', [
                'advance_id' => $advance->id,
                'advance_number' => $advance->advance_number,
                'approved_by' => $userId,
            ]);

            return $advance->fresh();
        });
    }

    /**
     * Record payment for an approved advance.
     * 
     * @param int $advanceId
     * @param array $paymentData
     * @return SupplierAdvance
     */
    public function recordAdvancePayment(int $advanceId, array $paymentData): SupplierAdvance
    {
        return DB::transaction(function () use ($advanceId, $paymentData) {
            $advance = SupplierAdvance::with('supplier')->lockForUpdate()->findOrFail($advanceId);

            if ($advance->status !== SupplierAdvance::STATUS_APPROVED) {
                throw new \Exception('Advance payment can only be recorded when in approved status');
            }

            $beforeState = $advance->toArray();

            $advance->update([
                'status' => SupplierAdvance::STATUS_PAID,
                'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                'payment_mode' => $paymentData['payment_mode'],
                'reference_number' => $paymentData['reference_number'] ?? null,
                'payment_proof_file' => $paymentData['payment_proof_file'] ?? null,
            ]);

            // Create audit log
            SupplierAdvanceAuditLog::log(
                $advance,
                SupplierAdvanceAuditLog::ACTION_ALLOCATION,
                $beforeState,
                $advance->fresh()->toArray(),
                $advance->amount,
                null,
                'Advance payment recorded',
                auth()->id()
            );

            // Trigger ledger entry (will be implemented in LedgerHelper)
            // LedgerHelper::createAdvancePaymentLedgerEntry($advance, $paymentData);

            Log::info('Advance payment recorded', [
                'advance_id' => $advance->id,
                'advance_number' => $advance->advance_number,
                'amount' => $advance->amount,
                'payment_mode' => $paymentData['payment_mode'],
            ]);

            return $advance->fresh();
        });
    }

    /**
     * Get total available advance balance for a supplier.
     * 
     * @param int $supplierId
     * @return float
     */
    public function getSupplierAvailableBalance(int $supplierId): float
    {
        return SupplierAdvance::forSupplier($supplierId)
            ->paid()
            ->withAvailableBalance()
            ->unlocked()
            ->get()
            ->sum(function ($advance) {
                return $advance->getAvailableBalanceAttribute();
            });
    }

    /**
     * Lock an advance for allocation.
     * 
     * @param int $advanceId
     * @return bool
     */
    public function lockAdvance(int $advanceId): bool
    {
        return DB::transaction(function () use ($advanceId) {
            $advance = SupplierAdvance::lockForUpdate()->findOrFail($advanceId);
            
            $beforeState = $advance->toArray();
            $result = $advance->lock();
            
            if ($result) {
                SupplierAdvanceAuditLog::log(
                    $advance,
                    SupplierAdvanceAuditLog::ACTION_LOCK,
                    $beforeState,
                    $advance->fresh()->toArray(),
                    null,
                    null,
                    'Advance locked for allocation',
                    auth()->id()
                );
            }
            
            return $result;
        });
    }

    /**
     * Unlock an advance after allocation.
     * 
     * @param int $advanceId
     * @return bool
     */
    public function unlockAdvance(int $advanceId): bool
    {
        return DB::transaction(function () use ($advanceId) {
            $advance = SupplierAdvance::lockForUpdate()->findOrFail($advanceId);
            
            $beforeState = $advance->toArray();
            $result = $advance->unlock();
            
            if ($result) {
                SupplierAdvanceAuditLog::log(
                    $advance,
                    SupplierAdvanceAuditLog::ACTION_UNLOCK,
                    $beforeState,
                    $advance->fresh()->toArray(),
                    null,
                    null,
                    'Advance unlocked after allocation',
                    auth()->id()
                );
            }
            
            return $result;
        });
    }

    /**
     * Generate a unique advance number.
     * 
     * @return string
     */
    private function generateAdvanceNumber(): string
    {
        $prefix = 'ADV';
        $year = now()->format('Y');
        $sequence = SupplierAdvance::whereYear('created_at', $year)->count() + 1;
        
        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }
}
