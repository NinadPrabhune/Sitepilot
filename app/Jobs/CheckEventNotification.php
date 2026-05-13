<?php

namespace App\Jobs;

use Workdo\Hrm\Entities\Event;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class CheckEventNotification implements ShouldQueue
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
        // Get upcoming events (today and within 7 days)
        $upcomingEvents = Event::query()
            ->whereDate('start_date', '>=', now())
            ->whereDate('start_date', '<=', now()->addDays(7))
            ->get();

        foreach ($upcomingEvents as $event) {
            // Get users who should be notified
            $userIds = $this->getNotifiableUsers($event);

            if (!empty($userIds)) {
                $notificationService->createEventNotification(
                    eventId: $event->id,
                    eventName: $event->title,
                    userIds: $userIds,
                    projectId: $event->site_id ?? null,
                    workspaceId: $event->workspace ?? null
                );
            }
        }
    }

    /**
     * Get user IDs that should be notified
     */
    private function getNotifiableUsers(Event $event): array
    {
        $userIds = [];

//        // Get event attendees
//        if ($event->attendees) {
//            $attendeeIds = json_decode($event->attendees, true) ?? [];
//            $userIds = array_merge($userIds, $attendeeIds);
//        }
//
//        // Also get workspace admins
//        $adminIds = \DB::table('model_has_roles')
//            ->join('users', 'model_has_roles.model_id', '=', 'users.id')
//            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
//            ->where('model_has_roles.model_type', 'App\Models\User')
//            ->where('roles.name', 'admin')
//            ->where('users.workspace_id', $event->workspace_id)
//            ->pluck('users.id')
//            ->toArray();
//
//        $userIds = array_merge($userIds, $adminIds);

        $userIds = User::pluck('id')->toArray();


        return array_unique($userIds);
    }
}
