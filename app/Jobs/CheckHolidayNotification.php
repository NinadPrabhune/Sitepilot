<?php

namespace App\Jobs;

use Workdo\Hrm\Entities\Holiday;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class CheckHolidayNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function handle(NotificationService $notificationService): void
    {
        // Get upcoming holidays (today and within 7 days)
        // Using start_date column (holidays table has: start_date, end_date, occasion)
        $upcomingHolidays = Holiday::query()
            ->whereDate('start_date', '>=', now())
            ->whereDate('start_date', '<=', now()->addDays(7))
            ->get();

        foreach ($upcomingHolidays as $holiday) {
            // Get users who should be notified
            $userIds = $this->getNotifiableUsers($holiday);

            if (!empty($userIds)) {
                $notificationService->createHolidayNotification(
                    holidayId: $holiday->id,
                    holidayName: $holiday->occasion,
                    userIds: $userIds,
                    projectId: $holiday->site_id ?? null,
                    workspaceId: $holiday->workspace ?? null
                );
            }
        }
    }

    /**
     * Get user IDs that should be notified
     */
    private function getNotifiableUsers(Holiday $holiday): array
    {
//        // Get all active users in the workspace
//        $userIds = \DB::table('users')
////            ->where('workspace_id', $holiday->workspace_id)
//            ->where('is_active', 1)
//            ->pluck('id')
//            ->toArray();


        $userIds = User::pluck('id')->toArray();

        return array_unique($userIds);
    }
}
