<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class PurchaseOrderObserver
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Purchase Order "created" event.
     */
    public function created(PurchaseOrder $po): void
    {
        try {
            $this->notificationService->createPOCreatedNotification($po);
            Log::info('PO created notification sent', ['po_id' => $po->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send PO created notification', [
                'po_id' => $po->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Purchase Order "updated" event.
     */
    public function updated(PurchaseOrder $po): void
    {
        try {
            // Early return if nothing changed
            if (!$po->wasChanged()) {
                return;
            }

            // Ignore if only updated_at changed
            if ($po->wasChanged(['updated_at'])) {
                return;
            }

            // Explicit status detection
            if ($po->wasChanged('status')) {
                $event = 'po.status_changed';
                $oldStatus = $po->getOriginal('status');
                $newStatus = $po->status;

                $this->notificationService->createPOStatusChangedNotification($po, $oldStatus, $newStatus, $event);
                Log::info('PO status changed notification sent', [
                    'po_id' => $po->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
                return; // IMPORTANT: avoid duplicate "updated"
            }

            // Regular update notification
            $event = 'po.updated';
            $this->notificationService->createPOUpdatedNotification($po, $event);
            Log::info('PO updated notification sent', ['po_id' => $po->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send PO updated notification', [
                'po_id' => $po->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
