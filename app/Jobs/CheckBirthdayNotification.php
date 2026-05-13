<?php

namespace App\Jobs;

use Workdo\Hrm\Entities\Employee; // ✅ Correct import
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class CheckBirthdayNotification implements ShouldQueue
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
        // Get all employees with birthday today
        $todayBirthdays = Employee::query()
            ->whereRaw('MONTH(dob) = MONTH(NOW())')
            ->whereRaw('DAY(dob) = DAY(NOW())')
            ->get();

        foreach ($todayBirthdays as $employee) {
            // Get users who should be notified (all workspace members)
            $userIds = $this->getNotifiableUsers($employee);

            if (!empty($userIds)) {
                $notificationService->createBirthdayNotification(
                    employeeId: $employee->id,
                    employeeName: $employee->name,
                    userIds: $userIds,
                    projectId: null,
                    workspaceId: $employee->workspace_id ?? null
                );
            }
        }
    }

    /**
     * Get user IDs that should be notified
     */
    private function getNotifiableUsers(Employee $employee): array
    {
        // Get all active users in the workspace
//        $userIds = \DB::table('users')
////            ->where('workspace_id', $employee->workspace_id)
//            ->where('is_active', 1)
//            ->pluck('id')
//            ->toArray();
        
        
        $userIds = User::pluck('id')->toArray();

        return array_unique($userIds);
    }
}
