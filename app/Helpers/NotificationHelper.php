<?php

namespace App\Helpers;

use App\Models\ChNotification;
use App\Models\ChNotificationUser;
use App\Services\NotificationService;

/**
 * Notification Helper Functions
 *
 * These helpers provide convenient shortcuts for working with notifications
 * throughout your application.
 */

class NotificationHelper
{
    /**
     * Get the notification service instance
     */
    public static function service(): NotificationService
    {
        return app(NotificationService::class);
    }

    /**
     * Notify low stock
     */
    public static function notifyLowStock(
        int $materialId,
        string $materialName,
        array $userIds,
        ?int $projectId = null,
        ?int $workspaceId = null
    ): ChNotification {
        return self::service()->createLowStockNotification(
            materialId: $materialId,
            materialName: $materialName,
            userIds: $userIds,
            projectId: $projectId,
            workspaceId: $workspaceId
        );
    }

    /**
     * Notify birthday
     */
    public static function notifyBirthday(
        int $employeeId,
        string $employeeName,
        array $userIds,
        ?int $projectId = null,
        ?int $workspaceId = null
    ): ChNotification {
        return self::service()->createBirthdayNotification(
            employeeId: $employeeId,
            employeeName: $employeeName,
            userIds: $userIds,
            projectId: $projectId,
            workspaceId: $workspaceId
        );
    }

    /**
     * Notify announcement
     */
    public static function notifyAnnouncement(
        int $announcementId,
        string $title,
        array $userIds,
        ?int $projectId = null,
        ?int $workspaceId = null
    ): ChNotification {
        return self::service()->createAnnouncementNotification(
            announcementId: $announcementId,
            title: $title,
            userIds: $userIds,
            projectId: $projectId,
            workspaceId: $workspaceId
        );
    }

    /**
     * Notify holiday
     */
    public static function notifyHoliday(
        int $holidayId,
        string $holidayName,
        array $userIds,
        ?int $projectId = null,
        ?int $workspaceId = null
    ): ChNotification {
        return self::service()->createHolidayNotification(
            holidayId: $holidayId,
            holidayName: $holidayName,
            userIds: $userIds,
            projectId: $projectId,
            workspaceId: $workspaceId
        );
    }

    /**
     * Notify event
     */
    public static function notifyEvent(
        int $eventId,
        string $eventName,
        array $userIds,
        ?int $projectId = null,
        ?int $workspaceId = null
    ): ChNotification {
        return self::service()->createEventNotification(
            eventId: $eventId,
            eventName: $eventName,
            userIds: $userIds,
            projectId: $projectId,
            workspaceId: $workspaceId
        );
    }

    /**
     * Custom notification
     */
    public static function notify(
        string $type,
        string $title,
        string $message,
        array $userIds,
        ?int $workspaceId = null,
        ?int $projectId = null,
        string $iconType = 'info',
        ?int $relatedId = null,
        ?string $relatedType = null,
        ?string $actionUrl = null
    ): ChNotification {
        return self::service()->create(
            type: $type,
            title: $title,
            message: $message,
            userIds: $userIds,
            workspaceId: $workspaceId,
            projectId: $projectId,
            iconType: $iconType,
            relatedId: $relatedId,
            relatedType: $relatedType,
            actionUrl: $actionUrl
        );
    }

    /**
     * Get unread count for user
     */
    public static function unreadCount(int $userId): int
    {
        return self::service()->countUnreadNotifications($userId);
    }

    /**
     * Get unread notifications for user
     */
    public static function unreadNotifications(int $userId, int $limit = 10)
    {
        return self::service()->getUnreadNotifications($userId, $limit);
    }

    /**
     * Get all notifications for user
     */
    public static function allNotifications(int $userId, int $limit = 20, int $offset = 0)
    {
        return self::service()->getAllNotifications($userId, $limit, $offset);
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead(int $userNotificationId, int $userId)
    {
        return self::service()->markAsRead($userNotificationId, $userId);
    }

    /**
     * Mark all notifications as read for user
     */
    public static function markAllAsRead(int $userId): int
    {
        return self::service()->markAllAsRead($userId);
    }

    /**
     * Delete notification
     */
    public static function delete(int $userNotificationId, int $userId): int
    {
        return self::service()->deleteNotification($userNotificationId, $userId);
    }

    /**
     * Delete all notifications for user
     */
    public static function deleteAll(int $userId): int
    {
        return self::service()->deleteAllNotifications($userId);
    }

    /**
     * Get workspace users (for notifications)
     */
    public static function getWorkspaceUsers(int $workspaceId): array
    {
        return \DB::table('users')
            ->where('workspace_id', $workspaceId)
            ->where('is_active', 1)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get users with role in workspace
     */
    public static function getUsersWithRole(int $workspaceId, string|array $roleName): array
    {
        $roles = is_array($roleName) ? $roleName : [$roleName];

        return \DB::table('model_has_roles')
            ->join('users', 'model_has_roles.model_id', '=', 'users.id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', 'App\Models\User')
            ->whereIn('roles.name', $roles)
            ->where('users.workspace_id', $workspaceId)
            ->pluck('users.id')
            ->toArray();
    }

    /**
     * Get admins in workspace
     */
    public static function getAdmins(int $workspaceId): array
    {
        return self::getUsersWithRole($workspaceId, 'admin');
    }

    /**
     * Get managers in workspace
     */
    public static function getManagers(int $workspaceId): array
    {
        return self::getUsersWithRole($workspaceId, ['admin', 'manager']);
    }

    /**
     * Get project members
     */
    public static function getProjectMembers(int $projectId): array
    {
        return \DB::table('project_members')
            ->where('project_id', $projectId)
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Get notifications by type
     */
    public static function getByType(string $type, int $userId, int $limit = 10)
    {
        return ChNotificationUser::whereHas('notification', function ($q) use ($type) {
            $q->where('type', $type);
        })
        ->where('user_id', $userId)
        ->with('notification')
        ->latest('created_at')
        ->limit($limit)
        ->get();
    }

    /**
     * Get notifications by date range
     */
    public static function getByDateRange(int $userId, $fromDate, $toDate, int $limit = 20)
    {
        return ChNotificationUser::where('user_id', $userId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->with('notification')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}

// ============================================================================
// GLOBAL HELPER FUNCTIONS
// ============================================================================

/**
 * Quick access to notification helper
 */
if (!function_exists('notify_helper')) {
    function notify_helper()
    {
        return new NotificationHelper();
    }
}

/**
 * Quick low stock notification
 */
if (!function_exists('notify_low_stock')) {
    function notify_low_stock($materialId, $materialName, $userIds, $projectId = null, $workspaceId = null)
    {
        return NotificationHelper::notifyLowStock($materialId, $materialName, $userIds, $projectId, $workspaceId);
    }
}

/**
 * Quick birthday notification
 */
if (!function_exists('notify_birthday')) {
    function notify_birthday($employeeId, $employeeName, $userIds, $projectId = null, $workspaceId = null)
    {
        return NotificationHelper::notifyBirthday($employeeId, $employeeName, $userIds, $projectId, $workspaceId);
    }
}

/**
 * Quick announcement notification
 */
if (!function_exists('notify_announcement')) {
    function notify_announcement($announcementId, $title, $userIds, $projectId = null, $workspaceId = null)
    {
        return NotificationHelper::notifyAnnouncement($announcementId, $title, $userIds, $projectId, $workspaceId);
    }
}

/**
 * Quick custom notification
 */
if (!function_exists('notify')) {
    function notify($type, $title, $message, $userIds, $workspaceId = null, $projectId = null, $iconType = 'info', $relatedId = null, $relatedType = null, $actionUrl = null)
    {
        return NotificationHelper::notify($type, $title, $message, $userIds, $workspaceId, $projectId, $iconType, $relatedId, $relatedType, $actionUrl);
    }
}

/**
 * Get unread count
 */
if (!function_exists('unread_count')) {
    function unread_count($userId)
    {
        return NotificationHelper::unreadCount($userId);
    }
}

/**
 * Get unread notifications
 */
if (!function_exists('unread_notifications')) {
    function unread_notifications($userId, $limit = 10)
    {
        return NotificationHelper::unreadNotifications($userId, $limit);
    }
}

/**
 * Get workspace users
 */
if (!function_exists('workspace_users')) {
    function workspace_users($workspaceId)
    {
        return NotificationHelper::getWorkspaceUsers($workspaceId);
    }
}

/**
 * Get users with role
 */
if (!function_exists('users_with_role')) {
    function users_with_role($workspaceId, $roleName)
    {
        return NotificationHelper::getUsersWithRole($workspaceId, $roleName);
    }
}

/**
 * Check if notifications are enabled globally
 */
if (!function_exists('notifications_enabled')) {
    function notifications_enabled(): bool
    {
        return config('app.send_notification') === true;
    }
}
