<?php

namespace App\Jobs;

use App\Domain\Machinery\Services\MachineryNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckMachineryDueDateNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->queue = 'notifications';
    }

    /**
     * Execute the job.
     */
    public function handle(MachineryNotificationService $notificationService): void
    {
        Log::info('Starting machinery due date notification check', [
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            $results = $notificationService->checkAndNotify();

            Log::info('Machinery due date notification check completed', [
                'puc_notifications' => $results['puc_notifications'],
                'service_notifications' => $results['service_notifications'],
                'errors' => $results['errors'],
                'timestamp' => now()->toDateTimeString(),
            ]);

            // Throw exception if there were critical errors
            if (!empty($results['errors'])) {
                throw new \Exception('Notification check completed with errors: ' . implode(', ', $results['errors']));
            }
        } catch (\Exception $e) {
            Log::error('Machinery due date notification check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Machinery due date notification job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}