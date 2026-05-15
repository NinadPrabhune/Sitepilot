<?php

namespace App\Domain\Machinery\Services;

use App\Models\ChNotification;
use App\Models\ChNotificationUser;
use App\Models\Machinery;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;

class MachineryNotificationService
{
    // Notification type constants
    const NOTIFICATION_TYPE_PUC = 'machinery_puc_due';
    const NOTIFICATION_TYPE_SERVICE = 'machinery_service_due';

    // Due date conditions
    const DUE_TODAY = 'today';
    const DUE_IN_3_DAYS = 'in_3_days';
    const OVERDUE = 'overdue';

    // Notification cooldown in days (prevent duplicate notifications)
    const NOTIFICATION_COOLDOWN_DAYS = 1;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Check and send notifications for machinery due dates
     */
    public function checkAndNotify(): array
    {
        $results = [
            'puc_notifications' => 0,
            'service_notifications' => 0,
            'errors' => [],
        ];

        try {
            // Process PUC due dates
            $pucResults = $this->processPucDueDates();
            $results['puc_notifications'] = $pucResults['sent'];

            // Process Service due dates
            $serviceResults = $this->processServiceDueDates();
            $results['service_notifications'] = $serviceResults['sent'];

            Log::info('Machinery due date notification check completed', $results);
        } catch (\Exception $e) {
            Log::error('Machinery due date notification check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Process PUC due dates and send notifications
     */
    protected function processPucDueDates(): array
    {
        $sent = 0;
        $processed = 0;

        $machineries = $this->getMachineryWithPucDue();

        foreach ($machineries as $machinery) {
            $processed++;
            $dueCondition = $this->getDueCondition($machinery->puc_due_date);

            if ($dueCondition) {
                // Check for duplicate notification
                if ($this->shouldSkipNotification($machinery->id, self::NOTIFICATION_TYPE_PUC, $dueCondition)) {
                    Log::debug('Skipping duplicate PUC notification', [
                        'machinery_id' => $machinery->id,
                        'condition' => $dueCondition,
                    ]);
                    continue;
                }

                $this->sendPucNotification($machinery, $dueCondition);
                $sent++;
            }
        }

        return ['processed' => $processed, 'sent' => $sent];
    }

    /**
     * Process Service due dates and send notifications
     */
    protected function processServiceDueDates(): array
    {
        $sent = 0;
        $processed = 0;

        $machineries = $this->getMachineryWithServiceDue();

        foreach ($machineries as $machinery) {
            $processed++;
            $dueCondition = $this->getDueCondition($machinery->maintenance_schedule);

            if ($dueCondition) {
                // Check for duplicate notification
                if ($this->shouldSkipNotification($machinery->id, self::NOTIFICATION_TYPE_SERVICE, $dueCondition)) {
                    Log::debug('Skipping duplicate service notification', [
                        'machinery_id' => $machinery->id,
                        'condition' => $dueCondition,
                    ]);
                    continue;
                }

                $this->sendServiceNotification($machinery, $dueCondition);
                $sent++;
            }
        }

        return ['processed' => $processed, 'sent' => $sent];
    }

    /**
     * Get machinery with PUC due dates matching notification criteria
     * Optimized query with proper filtering
     */
    protected function getMachineryWithPucDue(): Collection
    {
        $today = now()->toDateString();
        $threeDaysLater = now()->addDays(3)->toDateString();
        $yesterday = now()->subDay()->toDateString();

        return Machinery::where('status', 'active')
            ->whereNotNull('puc_due_date')
            ->where(function ($query) use ($today, $threeDaysLater, $yesterday) {
                $query->where('puc_due_date', $today)
                    ->orWhere('puc_due_date', $threeDaysLater)
                    ->orWhere('puc_due_date', '<', $today);
            })
            ->with(['site', 'category'])
            ->get();
    }

    /**
     * Get machinery with Service due dates matching notification criteria
     * Uses maintenance_schedule field as next service date
     */
    protected function getMachineryWithServiceDue(): Collection
    {
        $today = now()->toDateString();
        $threeDaysLater = now()->addDays(3)->toDateString();
        $yesterday = now()->subDay()->toDateString();

        return Machinery::where('status', 'active')
            ->whereNotNull('maintenance_schedule')
            ->where(function ($query) use ($today, $threeDaysLater, $yesterday) {
                $query->where('maintenance_schedule', $today)
                    ->orWhere('maintenance_schedule', $threeDaysLater)
                    ->orWhere('maintenance_schedule', '<', $today);
            })
            ->with(['site', 'category'])
            ->get();
    }

    /**
     * Determine the due condition based on date
     */
    protected function getDueCondition(?string $dueDate): ?string
    {
        if (empty($dueDate)) {
            return null;
        }

        $today = now()->toDateString();
        $threeDaysLater = now()->addDays(3)->toDateString();

        if ($dueDate === $today) {
            return self::DUE_TODAY;
        } elseif ($dueDate === $threeDaysLater) {
            return self::DUE_IN_3_DAYS;
        } elseif ($dueDate < $today) {
            return self::OVERDUE;
        }

        return null;
    }

    /**
     * Check if notification should be skipped (duplicate prevention)
     */
    protected function shouldSkipNotification(int $machineryId, string $type, string $condition): bool
    {
        // Check if we already sent a notification for this machinery/type/condition today
        $cutoffDate = now()->subDays(self::NOTIFICATION_COOLDOWN_DAYS)->toDateString();

        return ChNotification::where('type', $type)
            ->where('related_id', $machineryId)
            ->where('related_type', 'Machinery')
            ->whereRaw("JSON_EXTRACT(message_arr, '$.due_condition') = ?", ['"' . $condition . '"'])
            ->where('created_at', '>=', $cutoffDate)
            ->exists();
    }

    /**
     * Send PUC notification for machinery
     */
    protected function sendPucNotification(Machinery $machinery, string $condition): void
    {
        $message = $this->buildPucMessage($machinery, $condition);
        $title = $this->buildPucTitle($machinery, $condition);
        $iconType = $condition === self::OVERDUE ? 'warning' : 'info';

        $this->sendNotification(
            type: self::NOTIFICATION_TYPE_PUC,
            title: $title,
            message: $message,
            machinery: $machinery,
            condition: $condition,
            iconType: $iconType
        );

        Log::info('PUC notification sent', [
            'machinery_id' => $machinery->id,
            'machine_id' => $machinery->machine_id,
            'condition' => $condition,
            'due_date' => $machinery->puc_due_date,
        ]);
    }

    /**
     * Send Service notification for machinery
     */
    protected function sendServiceNotification(Machinery $machinery, string $condition): void
    {
        $message = $this->buildServiceMessage($machinery, $condition);
        $title = $this->buildServiceTitle($machinery, $condition);
        $iconType = $condition === self::OVERDUE ? 'warning' : 'info';

        $this->sendNotification(
            type: self::NOTIFICATION_TYPE_SERVICE,
            title: $title,
            message: $message,
            machinery: $machinery,
            condition: $condition,
            iconType: $iconType
        );

        Log::info('Service notification sent', [
            'machinery_id' => $machinery->id,
            'machine_id' => $machinery->machine_id,
            'condition' => $condition,
            'due_date' => $machinery->maintenance_schedule,
        ]);
    }

    /**
     * Build PUC notification message
     */
    protected function buildPucMessage(Machinery $machinery, string $condition): string
    {
        $machineIdentifier = $machinery->machine_id . ($machinery->vehicle_number ? " ({$machinery->vehicle_number})" : '');
        $dueDateFormatted = Carbon::parse($machinery->puc_due_date)->format('d M Y');

        switch ($condition) {
            case self::DUE_TODAY:
                return "PUC for Machinery {$machineIdentifier} is due today.";
            case self::DUE_IN_3_DAYS:
                return "PUC for Machinery {$machineIdentifier} is due in 3 days ({$dueDateFormatted}).";
            case self::OVERDUE:
                return "PUC for Machinery {$machineIdentifier} is overdue (was due on {$dueDateFormatted}).";
            default:
                return "PUC for Machinery {$machineIdentifier} needs attention.";
        }
    }

    /**
     * Build PUC notification title
     */
    protected function buildPucTitle(Machinery $machinery, string $condition): string
    {
        $siteName = $machinery->site?->name ?? 'Unknown Site';

        switch ($condition) {
            case self::DUE_TODAY:
                return "PUC Due Today - {$machinery->machine_id}";
            case self::DUE_IN_3_DAYS:
                return "PUC Due Soon - {$machinery->machine_id}";
            case self::OVERDUE:
                return "PUC Overdue - {$machinery->machine_id}";
            default:
                return "PUC Reminder - {$machinery->machine_id}";
        }
    }

    /**
     * Build Service notification message
     */
    protected function buildServiceMessage(Machinery $machinery, string $condition): string
    {
        $machineIdentifier = $machinery->machine_id . ($machinery->vehicle_number ? " ({$machinery->vehicle_number})" : '');
        $dueDateFormatted = Carbon::parse($machinery->maintenance_schedule)->format('d M Y');

        switch ($condition) {
            case self::DUE_TODAY:
                return "Service for Machinery {$machineIdentifier} is due today.";
            case self::DUE_IN_3_DAYS:
                return "Service for Machinery {$machineIdentifier} is due in 3 days ({$dueDateFormatted}).";
            case self::OVERDUE:
                return "Service for Machinery {$machineIdentifier} is overdue (was due on {$dueDateFormatted}).";
            default:
                return "Service for Machinery {$machineIdentifier} needs attention.";
        }
    }

    /**
     * Build Service notification title
     */
    protected function buildServiceTitle(Machinery $machinery, string $condition): string
    {
        switch ($condition) {
            case self::DUE_TODAY:
                return "Service Due Today - {$machinery->machine_id}";
            case self::DUE_IN_3_DAYS:
                return "Service Due Soon - {$machinery->machine_id}";
            case self::OVERDUE:
                return "Service Overdue - {$machinery->machine_id}";
            default:
                return "Service Reminder - {$machinery->machine_id}";
        }
    }

    /**
     * Send notification to all site users
     */
    protected function sendNotification(
        string $type,
        string $title,
        string $message,
        Machinery $machinery,
        string $condition,
        string $iconType = 'info'
    ): void {
        if (!$machinery->site_id) {
            Log::warning('Cannot send notification - no site associated', [
                'machinery_id' => $machinery->id,
                'machine_id' => $machinery->machine_id,
            ]);
            return;
        }

        // Get all users for the site
        $userIds = $this->getSiteUserIds($machinery->site_id);

        if (empty($userIds)) {
            Log::warning('No users found for site', [
                'site_id' => $machinery->site_id,
                'machinery_id' => $machinery->id,
            ]);
            return;
        }

        $project = Project::find($machinery->site_id);

        // Build meta array
        $messageArr = [
            'machinery_id' => $machinery->id,
            'machine_id' => $machinery->machine_id,
            'machinery_name' => $machinery->name,
            'vehicle_number' => $machinery->vehicle_number,
            'site_id' => $machinery->site_id,
            'site_name' => $project?->name,
            'due_condition' => $condition,
            'notification_type' => $type,
        ];

        // Add specific due date based on type
        if ($type === self::NOTIFICATION_TYPE_PUC) {
            $messageArr['puc_due_date'] = $machinery->puc_due_date;
        } else {
            $messageArr['service_due_date'] = $machinery->maintenance_schedule;
        }

        // Use NotificationService to create notification
        $this->notificationService->create(
            type: $type,
            title: $title,
            message: $message,
            userIds: $userIds,
            workspaceId: $machinery->workspace_id,
            projectId: $machinery->site_id,
            iconType: $iconType,
            relatedId: $machinery->id,
            relatedType: 'Machinery',
            actionUrl: $this->getActionUrl($machinery),
            messageArr: $messageArr
        );
    }

    /**
     * Get all user IDs for a site (same logic as NotificationService)
     */
    protected function getSiteUserIds(int $siteId): array
    {
        // 1. Users mapped via user_projects
        $projectUserIds = DB::table('user_projects')
            ->where('project_id', $siteId)
            ->pluck('user_id');

        // 2. Users with type = company OR Admin (direct from users table)
        $typeUserIds = DB::table('users')
            ->where('site_id', $siteId)
            ->whereIn('type', ['company', 'Admin'])
            ->pluck('id');

        // 3. Merge + Unique
        return $projectUserIds
            ->merge($typeUserIds)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get action URL for the machinery
     */
    protected function getActionUrl(Machinery $machinery): string
    {
        // Try to use route helper if available, otherwise return a generic URL
        try {
            if (route_exists('machinery.index')) {
                return route('machinery.index', ['site' => $machinery->site_id], false);
            }
        } catch (\Exception $e) {
            // Route not found, use fallback
        }

        return '/machinery?site=' . $machinery->site_id;
    }
}