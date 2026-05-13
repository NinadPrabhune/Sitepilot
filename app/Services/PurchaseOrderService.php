<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DomainException;

class PurchaseOrderService
{
    protected IndentService $indentService;

    public function __construct(IndentService $indentService)
    {
        $this->indentService = $indentService;
    }

    /**
     * Reject a purchase order with validation and indent status recalculation.
     *
     * @param int $poId
     * @param string|null $reason
     * @param int $userId
     * @return PurchaseOrder
     * @throws DomainException
     */
    public function reject(int $poId, ?string $reason, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($poId, $reason, $userId) {
            // 1. Lock PO row
            $po = PurchaseOrder::lockForUpdate()->findOrFail($poId);

            // 2. Idempotency check
            if ($po->status === PurchaseOrder::STATUS_REJECTED) {
                return $po;
            }

            // 3. Status transition guard (using model constant)
            if (!in_array($po->status, PurchaseOrder::REJECTABLE_STATUSES)) {
                throw new DomainException("Invalid status transition from {$po->status} to Rejected");
            }

            // 4. Validation checks (after lock, in service)
            $this->validateReject($po);

            // 5. Capture old status for logging
            $oldStatus = $po->status;

            // 6. Update PO status
            $po->update([
                'status' => PurchaseOrder::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // 7. Recalculate indent using fresh queries
            if ($po->indent) {
                $this->indentService->recalculate($po->indent->id);
            }

            // 8. Log status change via PoStatusLog (capture both old and new)
            $po->logStatusChange(PurchaseOrder::STATUS_REJECTED, $reason, $userId);

            // 9. Refresh to return fresh state
            $po->refresh();

            Log::info('Purchase Order rejected', [
                'po_id' => $po->id,
                'po_number' => $po->po_number,
                'old_status' => $oldStatus,
                'new_status' => $po->status,
                'rejected_by' => $userId,
                'reason' => $reason,
            ]);

            return $po;
        });
    }

    /**
     * Validate if a purchase order can be rejected.
     *
     * @param PurchaseOrder $po
     * @return void
     * @throws DomainException
     */
    private function validateReject(PurchaseOrder $po): void
    {
        if ($po->grns()->exists()) {
            throw new DomainException('Cannot reject PO with existing GRN');
        }

        if ($po->invoices()->exists()) {
            throw new DomainException('Cannot reject PO with existing invoices');
        }

        if ($po->payments()->exists()) {
            throw new DomainException('Cannot reject PO with existing payments');
        }

        if ($po->paymentRequests()->where('status', 'approved')->exists()) {
            throw new DomainException('Cannot reject PO with approved payment requests');
        }
    }
}
