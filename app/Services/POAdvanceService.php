<?php

namespace App\Services;

use App\Models\PaymentRequest;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POAdvanceService
{
    /**
     * Get modal data for PO advance request
     * 
     * @param int $poId
     * @return array
     */
    public function getModalData(int $poId): array
    {
        $po = PurchaseOrder::with(['supplier', 'site'])->findOrFail($poId);
        
        // Calculate existing advances against this PO
        $existingAdvances = PaymentRequest::forPo($poId)
            ->poAdvance()
            ->whereIn('status', ['approved', 'partially_approved', 'paid'])
            ->sum('requested_amount');
        
        // Calculate pending advances
        $pendingAdvances = PaymentRequest::forPo($poId)
            ->poAdvance()
            ->pending()
            ->sum('requested_amount');
        
        // Calculate available balance
        $availableBalance = $po->grand_total - $existingAdvances - $pendingAdvances;
        
        return [
            'po' => $po,
            'supplier' => $po->supplier,
            'site' => $po->site,
            'grand_total' => $po->grand_total,
            'existing_advances' => $existingAdvances,
            'pending_advances' => $pendingAdvances,
            'available_balance' => max(0, $availableBalance),
            'payment_terms_conditions' => $po->payment_terms_conditions,
        ];
    }

    /**
     * Validate advance request business rules
     * 
     * @param PurchaseOrder $po
     * @param int $percentage
     * @param float $advanceAmount
     * @return array
     * @throws \Exception
     */
    public function validateAdvanceRequest(PurchaseOrder $po, int $percentage, float $advanceAmount): array
    {
        $errors = [];
        
        // Validate percentage range (1-100)
        if ($percentage < 1 || $percentage > 100) {
            $errors[] = 'Percentage must be between 1 and 100.';
        }
        
        // Server-side validation: Check if PO payment is completed
        if ($po->isPaymentCompleted()) {
            $errors[] = 'Cannot request advance. PO already fully paid.';
        }
        
        // Server-side validation: Check if advance request already exists
        if ($po->hasAdvanceRequest()) {
            $errors[] = 'Only one advance request allowed per PO.';
        }
        
        // Calculate server-side advance amount
        $calculatedAmount = ($po->grand_total * $percentage) / 100;
        
        // Validate advance amount matches calculation (allow small floating point difference)
        if (abs($advanceAmount - $calculatedAmount) > 0.01) {
            $errors[] = 'Advance amount does not match calculated amount.';
        }
        
        // Validate advance amount <= grand_total
        if ($advanceAmount > $po->grand_total) {
            $errors[] = 'Advance amount cannot exceed PO total amount.';
        }
        
        // Get modal data to check available balance
        $modalData = $this->getModalData($po->id);
        
        if ($advanceAmount > $modalData['available_balance']) {
            $errors[] = 'Advance amount exceeds available balance.';
        }
        
        return $errors;
    }

    /**
     * Check pending requests to prevent overload
     * 
     * @param int $poId
     * @param float $newAdvanceAmount
     * @return array
     * @throws \Exception
     */
    public function checkPendingRequests(int $poId, float $newAdvanceAmount): array
    {
        $errors = [];
        
        // Lock PO row and payment_requests rows for same PO to prevent race conditions
        $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);
        
        // Lock payment_requests rows for this PO
        $pendingSum = DB::table('payment_requests')
            ->where('po_id', $poId)
            ->where('status', 'pending')
            ->where('type', PaymentRequest::TYPE_PO_ADVANCE)
            ->lockForUpdate()
            ->sum('requested_amount');
        
        // Critical check: SUM(pending PO advances) + new request <= grand_total
        if (($pendingSum + $newAdvanceAmount) > $po->grand_total) {
            $errors[] = 'Total pending advances would exceed PO total amount. Please wait for existing requests to be processed.';
        }
        
        return $errors;
    }

    /**
     * Create advance request with transaction locking
     * 
     * @param int $poId
     * @param int $percentage
     * @param float $advanceAmount
     * @param string|null $notes
     * @param int $userId
     * @return PaymentRequest
     * @throws \Exception
     */
    public function createAdvanceRequest(
        int $poId,
        int $percentage,
        float $advanceAmount,
        ?string $notes,
        string $paymentDate,
        int $userId
    ): PaymentRequest {
        return DB::transaction(function () use ($poId, $percentage, $advanceAmount, $notes, $paymentDate, $userId) {
            // Lock PO row
            $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);
            
            // Lock payment_requests rows for this PO
            $pendingSum = DB::table('payment_requests')
                ->where('po_id', $poId)
                ->where('status', 'pending')
                ->where('type', PaymentRequest::TYPE_PO_ADVANCE)
                ->lockForUpdate()
                ->sum('requested_amount');
            
            // Final validation before creation
            if (($pendingSum + $advanceAmount) > $po->grand_total) {
                throw new \Exception('Total pending advances would exceed PO total amount.');
            }
            
            // Create PaymentRequest record
            $paymentRequest = PaymentRequest::create([
                'po_id' => $poId,
                'purchase_invoice_id' => null, // Explicitly null for PO advances
                'type' => PaymentRequest::TYPE_PO_ADVANCE,
                'requested_amount' => $advanceAmount,
                'payment_date' => $paymentDate,
                'status' => PaymentRequest::STATUS_PENDING,
                'remarks' => $notes,
                'requested_by' => $userId,
            ]);
            
            Log::info('PO Advance Request created', [
                'payment_request_id' => $paymentRequest->id,
                'po_id' => $poId,
                'percentage' => $percentage,
                'advance_amount' => $advanceAmount,
                'requested_by' => $userId,
            ]);
            
            return $paymentRequest;
        });
    }
}
