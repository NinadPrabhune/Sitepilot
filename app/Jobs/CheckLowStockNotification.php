<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;

class CheckLowStockNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Set the queue name.
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
        Log::info('CheckLowStockNotification job started.');

        foreach (Project::cursor() as $project) {
            Log::info('Checking project', [
                'project_id'   => $project->id,
                'project_name' => $project->name,
            ]);

            // Get low stock materials
            $lowStockMaterials = getCurrentStockBySiteId($project->id)
                ->filter(fn($m) => $m->total_qty <= $m->reorder_level);

            Log::info('Low stock materials found', [
                'project_id' => $project->id,
                'count'      => $lowStockMaterials->count(),
                'materials'  => $lowStockMaterials->pluck('material_name')->toArray(),
            ]);

            if ($lowStockMaterials->isEmpty()) {
                continue;
            }

            // Get users to notify
            $userIds = $this->getNotifiableUsers($project->id, $project->workspace);
            Log::info('Users to notify', [
                'project_id' => $project->id,
                'user_ids'   => $userIds,
            ]);

            if (!empty($userIds)) {
                $notificationService->createLowStockNotification(
                    materials: $lowStockMaterials->all(), // convert to array
                    userIds: $userIds,
                    projectId: $project->id,
                    workspaceId: $project->workspace
                );
            }
        }

        Log::info('CheckLowStockNotification job finished.');
    }

    /**
     * Get user IDs that should be notified for a project.
     */
    private function getNotifiableUsers(int $projectId, ?int $workspaceId): array
    {
        $userIds = DB::table('user_projects')
            ->where('project_id', $projectId)
            ->pluck('user_id')
            ->toArray();

        Log::debug('UserProjects lookup', [
            'project_id' => $projectId,
            'user_ids'   => $userIds,
        ]);

        return array_unique($userIds);
    }
}
