<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAnnouncementNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $announcement;

    /**
     * Create a new job instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
        $this->queue = 'notifications';
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        // Get users who should be notified
        $userIds = $this->getNotifiableUsers();

        if (!empty($userIds)) {
            $notificationService->createAnnouncementNotification(
                announcementId: $this->announcement->id,
                title: $this->announcement->title,
                userIds: $userIds,
                projectId: $this->announcement->project_id ?? null,
                workspaceId: $this->announcement->workspace_id ?? null
            );
        }
    }

    /**
     * Get user IDs that should be notified
     */
    private function getNotifiableUsers(): array
    {
        // Get all active users in the workspace
        $userIds = \DB::table('users')
            ->where('workspace_id', $this->announcement->workspace_id)
            ->where('is_active', 1)
            ->pluck('id')
            ->toArray();

        return array_unique($userIds);
    }
}
