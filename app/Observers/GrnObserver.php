<?php

namespace App\Observers;

use App\Models\Grn;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class GrnObserver
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the GRN "created" event.
     */
    public function created(Grn $grn): void
    {
        try {
            $this->notificationService->createGrnCreatedNotification($grn);
            Log::info('GRN created notification sent', ['grn_id' => $grn->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send GRN created notification', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the GRN "updated" event.
     */
    public function updated(Grn $grn): void
    {
        try {
            // Early return if nothing changed
            if (!$grn->wasChanged()) {
                return;
            }

            // Ignore if only updated_at changed
            if ($grn->wasChanged(['updated_at'])) {
                return;
            }

            // Explicit status detection
            if ($grn->wasChanged('status')) {
                $event = 'grn.status_changed';
                $oldStatus = $grn->getOriginal('status');
                $newStatus = $grn->status;

                $this->notificationService->createGrnStatusChangedNotification($grn, $oldStatus, $newStatus, $event);
                Log::info('GRN status changed notification sent', [
                    'grn_id' => $grn->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
                return; // IMPORTANT: avoid duplicate "updated"
            }

            // Regular update notification
            $event = 'grn.updated';
            $this->notificationService->createGrnUpdatedNotification($grn, $event);
            Log::info('GRN updated notification sent', ['grn_id' => $grn->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send GRN updated notification', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
