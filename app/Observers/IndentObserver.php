<?php

namespace App\Observers;

use App\Models\Indent;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IndentObserver
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Indent "created" event.
     */
    public function created(Indent $indent): void
    {
        try {
            $this->notificationService->createIndentCreatedNotification($indent);
            Log::info('Indent created notification sent', ['indent_id' => $indent->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send indent created notification', [
                'indent_id' => $indent->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Indent "updated" event.
     */
    public function updated(Indent $indent): void
    {
        try {
            // Early return if nothing changed
            if (!$indent->wasChanged()) {
                return;
            }

            // Ignore if only updated_at changed
            if ($indent->wasChanged(['updated_at'])) {
                return;
            }

            // Explicit status detection
            if ($indent->wasChanged('status')) {
                $event = 'indent.status_changed';
                $oldStatus = $indent->getOriginal('status');
                $newStatus = $indent->status;

                $this->notificationService->createIndentStatusChangedNotification($indent, $oldStatus, $newStatus, $event);
                Log::info('Indent status changed notification sent', [
                    'indent_id' => $indent->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
                return; // IMPORTANT: avoid duplicate "updated"
            }

            // Regular update notification
            $event = 'indent.updated';
            $this->notificationService->createIndentUpdatedNotification($indent, $event);
            Log::info('Indent updated notification sent', ['indent_id' => $indent->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send indent updated notification', [
                'indent_id' => $indent->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
