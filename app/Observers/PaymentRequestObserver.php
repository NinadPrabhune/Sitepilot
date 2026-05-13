<?php

namespace App\Observers;

use App\Models\PaymentRequest;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class PaymentRequestObserver
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Payment Request "created" event.
     */
    public function created(PaymentRequest $paymentRequest): void
    {
        try {
            $this->notificationService->createPaymentRequestCreatedNotification($paymentRequest);
            Log::info('Payment request created notification sent', ['payment_request_id' => $paymentRequest->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment request created notification', [
                'payment_request_id' => $paymentRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Payment Request "updated" event.
     */
    public function updated(PaymentRequest $paymentRequest): void
    {
        try {
            // Early return if nothing changed
            if (!$paymentRequest->wasChanged()) {
                return;
            }

            // Ignore if only updated_at changed
            if ($paymentRequest->wasChanged(['updated_at'])) {
                return;
            }

            // Explicit status detection
            if ($paymentRequest->wasChanged('status')) {
                $event = 'payment_request.status_changed';
                $oldStatus = $paymentRequest->getOriginal('status');
                $newStatus = $paymentRequest->status;

                $this->notificationService->createPaymentRequestStatusChangedNotification($paymentRequest, $oldStatus, $newStatus, $event);
                Log::info('Payment request status changed notification sent', [
                    'payment_request_id' => $paymentRequest->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
                return; // IMPORTANT: avoid duplicate "updated"
            }

            // Regular update notification
            $event = 'payment_request.updated';
            $this->notificationService->createPaymentRequestUpdatedNotification($paymentRequest, $event);
            Log::info('Payment request updated notification sent', ['payment_request_id' => $paymentRequest->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment request updated notification', [
                'payment_request_id' => $paymentRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
