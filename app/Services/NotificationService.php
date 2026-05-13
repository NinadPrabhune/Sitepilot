<?php

namespace App\Services;

use App\Models\ChNotification;
use App\Models\ChNotificationUser;
use App\Models\User;
use App\Models\NotificationLog;
use App\Models\{
    PaymentRequest,
    PurchaseInvoice,
    Indent,
    PurchaseOrder,
    Grn
};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Workdo\Taskly\Entities\Project;
use Workdo\Hrm\Entities\AnnouncementEmployee;

class NotificationService {

    // Notification type constants
    const TYPE_INDENT_CREATED = 'indent_created';
    const TYPE_INDENT_UPDATED = 'indent_updated';
    const TYPE_INDENT_STATUS_CHANGED = 'indent_status_changed';
    const TYPE_PO_CREATED = 'po_created';
    const TYPE_PO_UPDATED = 'po_updated';
    const TYPE_PO_STATUS_CHANGED = 'po_status_changed';
    const TYPE_GRN_CREATED = 'grn_created';
    const TYPE_GRN_UPDATED = 'grn_updated';
    const TYPE_GRN_STATUS_CHANGED = 'grn_status_changed';
    const TYPE_INVOICE_CREATED = 'invoice_created';
    const TYPE_INVOICE_UPDATED = 'invoice_updated';
    const TYPE_INVOICE_STATUS_CHANGED = 'invoice_status_changed';
    const TYPE_PAYMENT_REQUEST_CREATED = 'payment_request_created';
    const TYPE_PAYMENT_REQUEST_UPDATED = 'payment_request_updated';
    const TYPE_PAYMENT_REQUEST_STATUS_CHANGED = 'payment_request_status_changed';
    const TYPE_PAYMENT_CREATED = 'payment_created';
    const TYPE_TASK_CREATED = 'task_created';
    const TYPE_TASK_UPDATED = 'task_updated';
    const TYPE_COMMENT_CREATED = 'comment_created';
    const TYPE_FILE_UPLOADED = 'file_uploaded';
    const TYPE_SUBTASK_CREATED = 'subtask_created';

    /**
     * Generate standardized notification message with site/workspace/datetime context
     */
    private function formatStandardMessage(
        string $siteName,
        ?string $workspaceName,
        string $recordIdentifier,
        string $action,
        ?string $user = null,
        ?string $oldStatus = null,
        ?string $newStatus = null
    ): array {
        $parts = [];
        if ($siteName) $parts[] = "[Site: {$siteName}]";
        if ($workspaceName) $parts[] = "[Workspace: {$workspaceName}]";
        $parts[] = "[{$recordIdentifier}]";

        $datetime = now()->format('d M Y, h:i A');

        switch ($action) {
            case 'created':
                $parts[] = "Created by {$user} on {$datetime}";
                break;
            case 'updated':
                $parts[] = "Updated by {$user} on {$datetime}";
                break;
            case 'status_changed':
                $parts[] = "Status changed from {$oldStatus} to {$newStatus} by {$user} on {$datetime}";
                break;
        }

        $formattedMessage = implode(' ', $parts);

        return [
            'message' => $formattedMessage,
            'meta' => [
                'site_name' => $siteName,
                'workspace_name' => $workspaceName,
                'record_identifier' => $recordIdentifier,
                'action' => $action,
                'user' => $user,
                'datetime' => $datetime,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]
        ];
    }

    /**
     * Resolve invoice identifier with safe fallback
     * Ensures we always return a non-null string for notification purposes
     */
    private function resolveInvoiceIdentifier(PurchaseInvoice $invoice): string
    {
        return $invoice->invoice_number 
            ?? $invoice->supplier_invoice_number 
            ?? 'INV-' . str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve indent identifier with safe fallback
     */
    private function resolveIndentIdentifier(Indent $indent): string
    {
        return $indent->indent_number ?? 'IND-' . str_pad((string) $indent->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve PO identifier with safe fallback
     */
    private function resolvePOIdentifier(PurchaseOrder $po): string
    {
        return $po->po_number ?? 'PO-' . str_pad((string) $po->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve GRN identifier with safe fallback
     */
    private function resolveGRNIdentifier(Grn $grn): string
    {
        return $grn->grn_number ?? 'GRN-' . str_pad((string) $grn->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get all users by site_id
     * @deprecated - Use resolveRecipients() instead for targeted notifications
     */
    public function getUsersBySiteId(int $siteId): Collection
    {
        // 1. Users mapped via user_projects
        $projectUserIds = DB::table('user_projects')
            ->where('project_id', $siteId)
            ->pluck('user_id');

        // 2. Users with type = company OR Admin (direct from users table)
        $typeUserIds = User::where('site_id', $siteId)
            ->whereIn('type', ['company', 'Admin'])
            ->pluck('id');

        // 3. Merge + Unique
        return $projectUserIds
            ->merge($typeUserIds)
            ->unique()
            ->values();
    }

    /**
     * Resolve recipients based on event and model with N+1 query prevention
     */
    private function resolveRecipients(string $event, $model): Collection
    {
        $userIds = collect();

        // 1. assign_to (PRIMARY)
        if (!empty($model->assign_to)) {
            $userIds->push($model->assign_to);
        }

        // 2. created_by / requested_by (SECONDARY)
        if (!empty($model->created_by)) {
            $userIds->push($model->created_by);
        }

        // 3. Event-specific logic
        if ($event === 'po.approved' || $event === 'po.rejected') {
            if (!empty($model->created_by)) {
                $userIds->push($model->created_by);
            }
        }

        // 4. GRN → PO creator mapping
        if ($event === 'grn.created' && $model->purchaseOrder) {
            $userIds->push($model->purchaseOrder->created_by);
        }

        // Single query to avoid N+1
        return User::whereIn('id', $userIds->filter()->unique())->get();
    }

    /**
     * Resolve PaymentRequest recipients with special handling
     */
    private function resolvePaymentRequestRecipients(string $event, PaymentRequest $model): Collection
    {
        $userIds = collect();

        if ($event === 'payment_request.created') {
            if (!empty($model->approved_by)) {
                $userIds->push($model->approved_by);
            } elseif (!empty($model->requested_by)) {
                // Fallback so request doesn't go unnoticed
                $userIds->push($model->requested_by);
            }
        }

        if (in_array($event, ['payment_request.approved', 'payment_request.rejected'])) {
            $userIds->push($model->requested_by);
        }

        // Single query to avoid N+1
        return User::whereIn('id', $userIds->filter()->unique())->get();
    }

    /**
     * Check if notification should be sent based on cooldown
     */
    private function shouldSend(int $userId, string $event, int $entityId): bool
    {
        $cooldown = config("notifications.cooldown.$event", 10);

        if ($cooldown === 0) {
            return true; // No cooldown for critical events
        }

        // Check notification_logs if table exists (for Step 3 compatibility)
        if (!\Illuminate\Support\Facades\Schema::hasTable('notification_logs')) {
            return true; // Skip cooldown check if table doesn't exist yet
        }

        return !DB::table('notification_logs')
            ->where([
                'user_id' => $userId,
                'event' => $event,
                'entity_id' => $entityId
            ])
            ->where('sent_at', '>', now()->subMinutes($cooldown))
            ->exists();
    }

    /**
     * Centralized send method for procurement notifications
     */
    public function send(
        string $type,
        string $title,
        string $message,
        int $siteId,
        array $meta = [],
        ?int $workspaceId = null,
        ?int $projectId = null,
        string $iconType = 'info',
        ?int $relatedId = null,
        ?string $relatedType = null,
        ?string $actionUrl = null,
        ?string $event = null,
        $model = null
    ): ?ChNotification {
        // Security validation: Ensure projectId matches siteId if provided
        if ($projectId !== null) {
            $projectExists = DB::table('projects')
                ->where('id', $projectId)
                ->where('id', $siteId) // Projects table uses id as site_id
                ->exists();

            if (!$projectExists) {
                Log::warning('Security violation: Project ID does not match site ID', [
                    'project_id' => $projectId,
                    'site_id' => $siteId,
                ]);
                return null;
            }
        }

        // Resolve recipients based on event and model if provided
        if ($event !== null && $model !== null) {
            if ($model instanceof PaymentRequest) {
                $users = $this->resolvePaymentRequestRecipients($event, $model);
            } else {
                $users = $this->resolveRecipients($event, $model);
            }

            $userIds = $users->pluck('id')->toArray();

            // Apply cooldown per user
            $finalUserIds = [];
            foreach ($userIds as $userId) {
                if ($this->shouldSend($userId, $event, $model->id)) {
                    $finalUserIds[] = $userId;
                }
            }

            // Skip if no valid recipients after cooldown check
            if (empty($finalUserIds)) {
                Log::info('Notification skipped - no valid recipients after cooldown', [
                    'event' => $event,
                    'entity_id' => $model->id,
                    'entity_type' => get_class($model),
                    'original_count' => count($userIds),
                ]);
                return null;
            }

            $userIds = $finalUserIds;
        } else {
            // Fallback to old method for backward compatibility
            $userIds = $this->getUsersBySiteId($siteId)->toArray();
        }

        // Prevent empty notifications
        if (empty($userIds)) {
            Log::warning('No users found for notification', ['site_id' => $siteId]);
            return null;
        }

        // Generate hash for idempotency (using projectId to prevent collisions across sites)
        // Add timestamp granularity for non-status updates to prevent logical collision
        $hash = md5(
            $type .
            $projectId .
            ($meta['record_id'] ?? '') .
            ($meta['action'] ?? '') .
            ($meta['new_status'] ?? '') .
            (($meta['action'] ?? '') === 'updated' ? microtime(true) : '')
        );

        // Duplicate guard
        if (ChNotification::where('hash', $hash)->exists()) {
            Log::info('Duplicate notification prevented by hash guard', ['hash' => $hash]);
            return null;
        }

        // Temporary debug logging for verification (only in debug mode)
        if (config('app.debug')) {
            Log::info('Notification sent', [
                'type' => $type,
                'project_id' => $projectId,
                'user_count' => count($userIds),
                'hash' => $hash,
            ]);
        }

        return $this->create(
            type: $type,
            title: $title,
            message: $message,
            messageArr: $meta,
            userIds: $userIds,
            workspaceId: $workspaceId,
            projectId: $projectId,
            iconType: $iconType,
            relatedId: $relatedId,
            relatedType: $relatedType,
            actionUrl: $actionUrl,
            hash: $hash
        );
    }

    /**
     * Create a notification and send it to specified users.
     */
    public function create(
            string $type,
            string $title,
            string $message,
            array $userIds,
            ?int $workspaceId = null,
            ?int $projectId = null,
            string $iconType = 'info',
            ?int $relatedId = null,
            ?string $relatedType = null,
            ?string $actionUrl = null,
            ?array $messageArr = null,
            ?string $hash = null
    ): ChNotification {
        $notification = ChNotification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'message_arr' => $messageArr,
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'icon_type' => $iconType,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'action_url' => $actionUrl,
            'hash' => $hash,
        ]);

        // Bulk insert for notification users
        $notificationUsers = collect($userIds)->map(fn($userId) => [
            'notification_id' => $notification->id,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        ChNotificationUser::insert($notificationUsers);

        // Log notification for each recipient (bulk insert for performance)
        if (\Illuminate\Support\Facades\Schema::hasTable('notification_logs')) {
            $logs = collect($userIds)->map(function ($userId) use ($type, $relatedType, $relatedId) {
                return [
                    'user_id' => $userId,
                    'event' => $type,
                    'entity_type' => $relatedType,
                    'entity_id' => $relatedId,
                    'sent_at' => now(),
                ];
            })->toArray();

            if (!empty($logs)) {
                NotificationLog::insert($logs);
            }
        }

        // Metrics logging
        Log::info('Notification created', [
            'type' => $type,
            'notification_id' => $notification->id,
            'project_id' => $projectId,
            'user_count' => count($userIds),
        ]);

        $users = User::whereIn('id', $userIds)->get();
        $this->sendFCMNotifications($notification, $users);

        // Broadcast AFTER DB insert for consistency with error handling
        // Guard: Check if notifications are enabled
        if (!config('app.send_notification')) {
            Log::info('Notifications disabled via SEND_NOTIFICATION flag - skipping Pusher broadcast', [
                'notification_id' => $notification->id,
            ]);
        } else {
            try {
                event(new \App\Events\NotificationCreatedEvent($notification));
                Log::info('Broadcast sent successfully', [
                    'notification_id' => $notification->id,
                    'project_id' => $projectId,
                ]);
            } catch (\Exception $e) {
                Log::error('Broadcast failed', [
                    'error' => $e->getMessage(),
                    'notification_id' => $notification->id,
                    'project_id' => $notification->project_id,
                ]);
                // Continue execution - notification is saved in DB, only real-time broadcast failed
            }
        }

        return $notification;
    }

    /**
     * Create a low stock notification.
     */
    public function createLowStockNotification(
            iterable $materials,
            array $userIds,
            ?int $projectId = null,
            ?int $workspaceId = null
    ): void {
        $projectName = $projectId ? DB::table('projects')->where('id', $projectId)->value('name') : 'Unknown Project';

        // Build HTML message
        $materialList = collect($materials)->map(fn($m) =>
                        "<li>{$m->material_name} (Qty: {$m->total_qty}, Reorder: {$m->reorder_level})</li>"
                )->implode('');

        $message = "
            <p>The following materials have reached their reorder level in project '{$projectName}':</p>
            <ul>{$materialList}</ul>
        ";

        // Build JSON array for message_arr
        $messageArr = collect($materials)->map(fn($m) => [
                    'material_name' => $m->material_name,
                    'total_qty' => $m->total_qty,
                    'reorder_level' => $m->reorder_level,
                        ])->toArray();

        foreach ($userIds as $userId) {
            Log::info('Creating batch low stock notification', [
                'project_id' => $projectId,
                'project_name' => $projectName,
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'materials' => collect($materials)->pluck('material_name')->toArray(),
                'message' => $message,
                'message_arr' => $messageArr,
            ]);

            $this->create(
                    type: 'low_stock',
                    title: "Low Stock Alert – {$projectName}",
                    message: $message,
                    userIds: [$userId],
                    workspaceId: $workspaceId,
                    projectId: $projectId,
                    iconType: 'warning',
                    relatedId: null,
                    relatedType: 'Material',
                    actionUrl: route('stock-reports.index', $projectId, false),
                    messageArr: $messageArr
            );
        }
    }

    /** Birthday Notification */
    public function createBirthdayNotification(
            int $employeeId,
            string $employeeName,
            array $userIds,
            ?int $projectId = null,
            ?int $workspaceId = null
    ): ChNotification {
        return $this->create(
                        type: 'birthday',
                        title: 'Birthday Reminder',
                        message: "Today is {$employeeName}'s birthday! 🎉",
                        userIds: $userIds,
                        workspaceId: $workspaceId,
                        projectId: $projectId,
                        iconType: 'success',
                        relatedId: $employeeId,
                        relatedType: 'Employee',
                        actionUrl: route('employee.show', $employeeId, false)
                );
    }

    /** Announcement Notification */
    public function createAnnouncementNotification(
            int $announcementId,
            string $title,
            array $userIds,
            ?int $projectId = null,
            ?int $workspaceId = null
    ): ChNotification {
        return $this->create(
                        type: 'announcement',
                        title: 'New Announcement',
                        message: $title,
                        userIds: $userIds,
                        workspaceId: $workspaceId,
                        projectId: $projectId,
                        iconType: 'info',
                        relatedId: $announcementId,
                        relatedType: 'Announcement',
                        actionUrl: route('announcement.show', $announcementId, false)
                );
    }

    /** Holiday Notification */
    public function createHolidayNotification(
            int $holidayId,
            string $holidayName,
            array $userIds,
            ?int $projectId = null,
            ?int $workspaceId = null
    ): ChNotification {
        return $this->create(
                        type: 'holiday',
                        title: 'Holiday Reminder',
                        message: "Upcoming holiday: {$holidayName}",
                        userIds: $userIds,
                        workspaceId: $workspaceId,
                        projectId: $projectId,
                        iconType: 'info',
                        relatedId: $holidayId,
                        relatedType: 'Holiday',
                        actionUrl: route('holiday.show', $holidayId, false)
                );
    }

    /** Event Notification */
    public function createEventNotification(
            int $eventId,
            string $eventName,
            array $userIds,
            ?int $projectId = null,
            ?int $workspaceId = null
    ): ChNotification {
        return $this->create(
                        type: 'event',
                        title: 'Event Reminder',
                        message: "Upcoming event: {$eventName}",
                        userIds: $userIds,
                        workspaceId: $workspaceId,
                        projectId: $projectId,
                        iconType: 'info',
                        relatedId: $eventId,
                        relatedType: 'Event',
                        actionUrl: route('event.show', $eventId, false)
                );
    }

    /**
     * Send FCM notifications.
     */
    public function sendFCMNotifications(ChNotification $notification, Collection|array $users): void {
        // Guard: Check if notifications are enabled
        if (!config('app.send_notification')) {
            Log::info('Notifications disabled via SEND_NOTIFICATION flag - skipping FCM send', [
                'notification_id' => $notification->id,
            ]);
            return;
        }

        try {
            $fcmService = app(FCMService::class);
            $users = is_array($users) ? collect($users) : $users;

            // Preload deviceTokens to avoid N+1 queries
            $users = $users->load('deviceTokens');

            // Collect all device tokens from all users
            $allTokens = [];
            $users->each(function (User $user) use (&$allTokens) {
                $tokens = $user->deviceTokens->pluck('token')->toArray();
                $allTokens = array_merge($allTokens, $tokens);
            });

            // Send bulk notification if tokens exist
            if (!empty($allTokens)) {
                $fcmService->sendBulkNotification(
                    deviceTokens: $allTokens,
                    title: $notification->title,
                    message: $notification->message,
                    data: [
                        'notification_id' => $notification->id,
                        'type' => $notification->type,
                        'action_url' => $notification->action_url,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('FCM Notification Error: ' . $e->getMessage());
        }
    }

    /**
     * Get unread notifications for a user.
     */
    public function getUnreadNotifications(int $userId, ?int $limit = 10): LengthAwarePaginator {
        return ChNotificationUser::query()
                        ->where('user_id', $userId)
                        ->whereNull('read_at')
                        ->with('notification')
                        ->latest('created_at')
                        ->paginate($limit);
    }

    /**
     * Get unread notifications for a user (no pagination).
     */
    public function getUnreadNotificationsHeader($userId, $limit = 10) {
        return ChNotificationUser::query()
                        ->where('user_id', $userId)
                        ->whereNull('read_at')
                        ->with('notification')
                        ->latest('created_at')
                        ->limit($limit)
                        ->get()
                        ->map(function ($notif) {
                            return [
                                'id' => $notif->id,
                                'user_notif_id' => $notif->id,
                                'read' => !is_null($notif->read_at),
                                'title' => $notif->notification->title ?? '',
                                'message' => $notif->notification->message ?? '',
                                'time' => $notif->created_at->diffForHumans(),
                                'type' => $notif->notification->type ?? 'info',
                                'icon_type' => $notif->notification->icon_type ?? 'info',
                            ];
                        });
    }

    /**
     * Get all notifications for a user.
     */
    public function getAllNotifications(int $userId, ?int $limit = 20, ?int $offset = 0): Collection {
        return ChNotificationUser::query()
                        ->where('user_id', $userId)
                        ->with('notification')
                        ->latest('created_at')
                        ->limit($limit)
                        ->offset($offset)
                        ->get()
                        ->map(fn(ChNotificationUser $userNotif) => $this->formatNotification($userNotif));
    }

    /**
     * Count unread notifications for a user.
     */
    public function countUnreadNotifications(int $userId): int {
        return ChNotificationUser::query()
                        ->where('user_id', $userId)
                        ->whereNull('read_at')
                        ->count();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(int $userNotificationId, int $userId): ?bool {
        $userNotif = ChNotificationUser::where('id', $userNotificationId)
                ->where('user_id', $userId)
                ->first();

        if ($userNotif) {
            return $userNotif->markAsRead();
        }

        return null;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(int $userId): int {
        return ChNotificationUser::where('user_id', $userId)
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);
    }

    /**
     * Delete a user notification.
     */
    public function deleteNotification(int $userNotificationId, int $userId): int {
        return ChNotificationUser::where('id', $userNotificationId)
                        ->where('user_id', $userId)
                        ->delete();
    }

    /**
     * Delete all notifications for a user.
     */
    public function deleteAllNotifications(int $userId): int {
        return ChNotificationUser::where('user_id', $userId)->delete();
    }

    /**
     * Helper: Format notification for API responses.
     */
    private function formatNotification(ChNotificationUser $userNotif): array {
        return [
            'id' => $userNotif->notification->id,
            'user_notif_id' => $userNotif->id,
            'type' => $userNotif->notification->type,
            'title' => $userNotif->notification->title,
            'message' => $userNotif->notification->message,
            'message_arr' => $userNotif->notification->message_arr,
            'icon_type' => $userNotif->notification->icon_type,
            'action_url' => $userNotif->notification->full_action_url,
            'time' => $userNotif->created_at->diffForHumans(),
            'read' => $userNotif->isRead(),
        ];
    }

    public function createPOGeneratedNotification(int $poId, int $projectId, string $invoiceNumber, string $requestedBy): void {
        try {
            Log::info('PO Notification Triggered', [
                'po_id' => $poId,
                'project_id' => $projectId
            ]);

            $project = DB::table('projects')->find($projectId);

            // ✅ Get all users by site_id instead of Account Managers only
            $userIds = $this->getUsersBySiteId($projectId)->toArray();

            Log::info('All users for site', [
                'project_id' => $projectId,
                'user_ids' => $userIds
            ]);

            // ✅ Delete existing notifications + user mappings
            $existingNotifications = ChNotification::where('type', 'po_generated')
                    ->where('related_id', $poId)
                    ->where('related_type', 'PurchaseOrder')
                    ->where('project_id', $projectId)
                    ->get();

            foreach ($existingNotifications as $existing) {
                ChNotificationUser::where('notification_id', $existing->id)->delete();
                $existing->delete();
            }

            Log::info('Existing notifications deleted', [
                'po_id' => $poId,
                'project_id' => $projectId
            ]);

            // ✅ Build HTML message (without materials list)
            $htmlMessage = "<p>A new PO has been created for project {$project->name}.</p>";
            $htmlMessage .= "<p>Invoice Number: {$invoiceNumber}, Requested By: {$requestedBy}</p>";

            // ✅ Create new notification for all site users
            $notification = $this->create(
                    type: 'po_generated',
                    title: "New Purchase Order – {$project->name}",
                    message: $htmlMessage,
                    messageArr: [
                        'po_id' => $poId,
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'invoice_number' => $invoiceNumber,
                        'requested_by' => $requestedBy,
                    ],
                    userIds: $userIds,
                    workspaceId: $project->workspace ?? null,
                    projectId: $project->id,
                    iconType: 'info',
                    relatedId: $poId,
                    relatedType: 'PurchaseOrder',
                    actionUrl: route('payment-request.index', $poId, false)
            );

            Log::info('Notification created', ['id' => $notification->id]);
        } catch (\Exception $e) {
            Log::error('Notification insert failed: ' . $e->getMessage());
        }
    }

    public function createPaymentRequestNotification(int $poId, int $projectId, string $invoiceNumber, string $requestedBy): void {
        try {
            Log::info('Payment Request Notification Triggered', [
                'po_id' => $poId,
                'project_id' => $projectId,
                'invoice_number' => $invoiceNumber,
                'requested_by' => $requestedBy
            ]);

            $project = DB::table('projects')->find($projectId);

            // ✅ Get all users by site_id instead of Account Managers only
            $userIds = $this->getUsersBySiteId($projectId)->toArray();

            Log::info('All users for site', [
                'project_id' => $projectId,
                'user_ids' => $userIds
            ]);

            // ✅ Delete existing Payment Request notifications for this PO + project
            $existingNotifications = ChNotification::where('type', 'payment_request')
                    ->where('related_id', $poId)
                    ->where('related_type', 'PurchaseOrder')
                    ->where('project_id', $projectId)
                    ->get();

            foreach ($existingNotifications as $existing) {
                ChNotificationUser::where('notification_id', $existing->id)->delete();
                $existing->delete();
            }

            Log::info('Existing Payment Request notifications deleted', [
                'po_id' => $poId,
                'project_id' => $projectId
            ]);

            // ✅ Build HTML message (without materials list)
            $htmlMessage = "<p>A new Payment Request has been created for project {$project->name}.</p>";
            $htmlMessage .= "<p>Invoice Number: {$invoiceNumber}, Requested By: {$requestedBy}</p>";

            // ✅ Create new Payment Request notification for all site users
            $notification = $this->create(
                    type: 'payment_request',
                    title: "New Payment Request – {$project->name}",
                    message: $htmlMessage,
                    messageArr: [
                        'po_id' => $poId,
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'invoice_number' => $invoiceNumber,
                        'requested_by' => $requestedBy,
                    ],
                    userIds: $userIds,
                    workspaceId: $project->workspace ?? null,
                    projectId: $project->id,
                    iconType: 'info',
                    relatedId: $poId,
                    relatedType: 'PurchaseOrder',
                    actionUrl: route('payment-request.index', $poId, false)
            );

            Log::info('Payment Request Notification created', ['id' => $notification->id]);
        } catch (\Exception $e) {
            Log::error('Payment Request Notification insert failed: ' . $e->getMessage());
        }
    }

//    public function createPaymentApprovalStatusNotification(
//            PurchaseInvoice $invoice,
//            int $projectId,
//            string $status,
//            ?string $rejectionReason = null
//    ): void {
//        $project = Project::find($projectId);
//
//        if (!$project) {
//            return;
//        }
//
//        // Build message and title based on status
//        switch ($status) {
//            case 'approved':
//                $title = "Invoice Approved – {$invoice->invoice_number}";
//                $htmlMessage = "<p>Invoice #{$invoice->invoice_number} for project {$project->name} has been <strong>approved</strong>.</p>";
//                break;
//
//            case 'partially_approved':
//                $title = "Invoice Partially Approved – {$invoice->invoice_number}";
//                $htmlMessage = "<p>Invoice #{$invoice->invoice_number} for project {$project->name} has been <strong>partially approved</strong>.</p>";
//                break;
//
//            case 'rejected':
//                $title = "Invoice Rejected – {$invoice->invoice_number}";
//                $htmlMessage = "<p>Invoice #{$invoice->invoice_number} for project {$project->name} has been <strong>rejected</strong>.</p>";
//                if ($rejectionReason) {
//                    $htmlMessage .= "<p>Reason: {$rejectionReason}</p>";
//                }
//                break;
//
//            default:
//                return; // invalid status
//        }
//
//        // Notify the invoice creator
//        $creator = $invoice->creator; // assumes PurchaseInvoice has creator() relationship
//        if (!$creator) {
//            return;
//        }
//
//        // Create notification
//        $notification = $this->create(
//                type: 'invoice_approval',
//                title: $title,
//                message: $htmlMessage,
//                messageArr: [
//                    'invoice_id' => $invoice->id,
//                    'invoice_number' => $invoice->invoice_number,
//                    'project_id' => $project->id,
//                    'project_name' => $project->name,
//                    'status' => $status,
//                    'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
//                ],
//                userIds: [$creator->id],
//                workspaceId: $project->workspace ?? null,
//                projectId: $project->id,
//                iconType: 'info',
//                relatedId: $invoice->id,
//                relatedType: 'PurchaseInvoice',
//                actionUrl: route('purchase-invoice.show', $invoice->id, false)
//        );
//
//        Log::info('Payment approval notification sent', ['id' => $notification->id]);
//    }

    public function createPaymentApprovalStatusNotification(
            PurchaseInvoice $invoice,
            int $projectId,
            string $status,
            ?string $rejectionReason = null,
            ?string $approvedBy = null
    ): void {
        $project = Project::find($projectId);

        if (!$project) {
            return;
        }

        switch ($status) {
            case 'approved':
                $title = "Payment Request Approved – {$invoice->invoice_number}";
                $htmlMessage = "<p>The payment request for Purchase Invoice #{$invoice->invoice_number}, associated with project {$project->name}, has been <strong>approved</strong>.</p>";
                if ($approvedBy) {
                    $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                }
                break;

            case 'partially_approved':
                $title = "Payment Request Partially Approved – {$invoice->invoice_number}";
                $htmlMessage = "<p>The payment request for Purchase Invoice #{$invoice->invoice_number}, associated with project {$project->name}, has been <strong>partially approved</strong>.</p>";
                if ($approvedBy) {
                    $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                }
                break;

            case 'rejected':
                $title = "Payment Request Rejected – {$invoice->invoice_number}";
                $htmlMessage = "<p>The payment request for Purchase Invoice #{$invoice->invoice_number}, associated with project {$project->name}, has been <strong>rejected</strong>.</p>";
                if ($rejectionReason) {
                    $htmlMessage .= "<p>Reason for rejection: {$rejectionReason}</p>";
                }
                if ($approvedBy) {
                    $htmlMessage .= "<p>Rejected by: {$approvedBy}</p>";
                }
                break;

            default:
                return;
        }

        // ✅ Get all users by site_id instead of creator only
        $userIds = $this->getUsersBySiteId($projectId)->toArray();

        $this->create(
                type: 'payment_approval',
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'status' => $status,
                    'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
                    'approved_by' => $approvedBy,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: $invoice->id,
                relatedType: 'PurchaseInvoice',
                actionUrl: route('purchase-invoice.show', $invoice->id, false)
        );
    }

    /**
     * Create task creation notification
     */
    public function createTaskCreatedNotification(
        string $taskTitle,
        int $projectId,
        array $assignToUserIds,
        int $createdBy,
        string $createdByName
    ): void {
        try {
            $project = Project::find($projectId);

            if (!$project) {
                return;
            }

            // Merge assign_to users and created_by user
            $userIds = array_unique(array_merge($assignToUserIds, [$createdBy]));

            $title = "New Task Assigned – {$project->name}";
            $htmlMessage = "<p>A new task has been assigned to you in project {$project->name}.</p>";
            $htmlMessage .= "<p>Task: {$taskTitle}</p>";
            $htmlMessage .= "<p>Assigned by: {$createdByName}</p>";

            $this->create(
                type: self::TYPE_TASK_CREATED,
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'task_title' => $taskTitle,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'assigned_by' => $createdByName,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: null,
                relatedType: 'Task',
                actionUrl: route('projecttask.list', $project->id, false)
            );

            Log::info('Task creation notification sent', ['project_id' => $projectId]);
        } catch (\Exception $e) {
            Log::error('Task creation notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create task update notification
     */
    public function createTaskUpdatedNotification(
        string $taskTitle,
        int $projectId,
        array $assignToUserIds,
        int $updatedBy,
        string $updatedByName
    ): void {
        try {
            $project = Project::find($projectId);

            if (!$project) {
                return;
            }

            // Merge assign_to users and updated_by user
            $userIds = array_unique(array_merge($assignToUserIds, [$updatedBy]));

            $title = "Task Updated – {$project->name}";
            $htmlMessage = "<p>A task has been updated in project {$project->name}.</p>";
            $htmlMessage .= "<p>Task: {$taskTitle}</p>";
            $htmlMessage .= "<p>Updated by: {$updatedByName}</p>";

            $this->create(
                type: self::TYPE_TASK_UPDATED,
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'task_title' => $taskTitle,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'updated_by' => $updatedByName,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: null,
                relatedType: 'Task',
                actionUrl: route('projecttask.list', $project->id, false)
            );

            Log::info('Task update notification sent', ['project_id' => $projectId]);
        } catch (\Exception $e) {
            Log::error('Task update notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create comment notification
     */
    public function createCommentNotification(
        string $taskTitle,
        int $projectId,
        int $taskId,
        array $assignToUserIds,
        int $createdBy,
        string $createdByName,
        string $comment
    ): void {
        try {
            $project = Project::find($projectId);

            if (!$project) {
                return;
            }

            // Merge assign_to users and created_by user
            $userIds = array_unique(array_merge($assignToUserIds, [$createdBy]));

            $title = "New Comment Added – {$project->name}";
            $htmlMessage = "<p>A new comment has been added to task in project {$project->name}.</p>";
            $htmlMessage .= "<p>Task: {$taskTitle}</p>";
            $htmlMessage .= "<p>Comment: " . substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : '') . "</p>";
            $htmlMessage .= "<p>Commented by: {$createdByName}</p>";

            $this->create(
                type: self::TYPE_COMMENT_CREATED,
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'task_title' => $taskTitle,
                    'task_id' => $taskId,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'commented_by' => $createdByName,
                    'comment' => $comment,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: $taskId,
                relatedType: 'Task',
                actionUrl: route('projecttask.list', $project->id, false)
            );

            Log::info('Comment notification sent', ['project_id' => $projectId, 'task_id' => $taskId]);
        } catch (\Exception $e) {
            Log::error('Comment notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create file upload notification
     */
    public function createFileUploadNotification(
        string $taskTitle,
        int $projectId,
        int $taskId,
        array $assignToUserIds,
        int $uploadedBy,
        string $uploadedByName,
        string $fileName
    ): void {
        try {
            $project = Project::find($projectId);

            if (!$project) {
                return;
            }

            // Merge assign_to users and uploaded_by user
            $userIds = array_unique(array_merge($assignToUserIds, [$uploadedBy]));

            $title = "File Uploaded – {$project->name}";
            $htmlMessage = "<p>A new file has been uploaded to task in project {$project->name}.</p>";
            $htmlMessage .= "<p>Task: {$taskTitle}</p>";
            $htmlMessage .= "<p>File: {$fileName}</p>";
            $htmlMessage .= "<p>Uploaded by: {$uploadedByName}</p>";

            $this->create(
                type: self::TYPE_FILE_UPLOADED,
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'task_title' => $taskTitle,
                    'task_id' => $taskId,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'uploaded_by' => $uploadedByName,
                    'file_name' => $fileName,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: $taskId,
                relatedType: 'Task',
                actionUrl: route('projecttask.list', $project->id, false)
            );

            Log::info('File upload notification sent', ['project_id' => $projectId, 'task_id' => $taskId]);
        } catch (\Exception $e) {
            Log::error('File upload notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create subtask notification
     */
    public function createSubtaskNotification(
        string $taskTitle,
        int $projectId,
        int $taskId,
        string $subtaskName,
        array $assignToUserIds,
        int $createdBy,
        string $createdByName
    ): void {
        try {
            $project = Project::find($projectId);

            if (!$project) {
                return;
            }

            // Merge assign_to users and created_by user
            $userIds = array_unique(array_merge($assignToUserIds, [$createdBy]));

            $title = "Subtask Created – {$project->name}";
            $htmlMessage = "<p>A new subtask has been created in project {$project->name}.</p>";
            $htmlMessage .= "<p>Task: {$taskTitle}</p>";
            $htmlMessage .= "<p>Subtask: {$subtaskName}</p>";
            $htmlMessage .= "<p>Created by: {$createdByName}</p>";

            $this->create(
                type: self::TYPE_SUBTASK_CREATED,
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'task_title' => $taskTitle,
                    'task_id' => $taskId,
                    'subtask_name' => $subtaskName,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'created_by' => $createdByName,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: $taskId,
                relatedType: 'Task',
                actionUrl: route('projecttask.list', $project->id, false)
            );

            Log::info('Subtask notification sent', ['project_id' => $projectId, 'task_id' => $taskId]);
        } catch (\Exception $e) {
            Log::error('Subtask notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create payment creation notification
     */
    public function createPaymentNotification(
        \App\Models\PaymentsModule $payment,
        int $projectId,
        ?string $paymentType = null
    ): void {
        try {
            $project = Project::find($projectId);

            if (!$project) {
                return;
            }

            // Get all users by site_id
            $userIds = $this->getUsersBySiteId($projectId)->toArray();

            $title = "Payment Created – {$payment->payment_number}";
            $htmlMessage = "<p>A new payment has been recorded for project {$project->name}.</p>";
            $htmlMessage .= "<p>Payment Number: {$payment->payment_number}, Amount: ₹" . number_format($payment->amount, 2) . "</p>";

            if ($paymentType) {
                $htmlMessage .= "<p>Payment Type: {$paymentType}</p>";
            }

            $this->create(
                type: self::TYPE_PAYMENT_CREATED,
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => $payment->amount,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'payment_type' => $paymentType,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'success',
                relatedId: $payment->id,
                relatedType: 'PaymentsModule',
                actionUrl: route('payments-module.index', [], false)
            );

            Log::info('Payment creation notification sent', ['payment_id' => $payment->id]);
        } catch (\Exception $e) {
            Log::error('Payment creation notification failed: ' . $e->getMessage());
        }
    }

    public function createPaymentApprovalNotification(
            PaymentRequest $paymentRequest,
            int $projectId,
            string $status,
            ?string $rejectionReason = null,
            ?string $approvedBy = null
    ): void {
        $project = Project::find($projectId);

        if (!$project) {
            return;
        }

        // ✅ Get all users by site_id instead of creator only
        $userIds = $this->getUsersBySiteId($projectId)->toArray();

        if ($paymentRequest->isPoAdvance()) {
            // PO advance context
            $po = $paymentRequest->po;
            if (!$po) {
                return;
            }

            switch ($status) {
                case 'approved':
                    $title = "Advance Approved – {$po->po_number}";
                    $htmlMessage = "<p>The advance request for Purchase Order #{$po->po_number}, associated with project {$project->name}, has been <strong>approved</strong>.</p>";
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                    }
                    break;

                case 'partially_approved':
                    $title = "Advance Partially Approved – {$po->po_number}";
                    $htmlMessage = "<p>The advance request for Purchase Order #{$po->po_number}, associated with project {$project->name}, has been <strong>partially approved</strong>.</p>";
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                    }
                    break;

                case 'rejected':
                    $title = "Advance Request Rejected – {$po->po_number}";
                    $htmlMessage = "<p>The advance request for Purchase Order #{$po->po_number}, associated with project {$project->name}, has been <strong>rejected</strong>.</p>";
                    if ($rejectionReason) {
                        $htmlMessage .= "<p>Reason for rejection: {$rejectionReason}</p>";
                    }
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Rejected by: {$approvedBy}</p>";
                    }
                    break;

                case 'batch_approved':
                    $title = "Batch Advance Approval – {$po->po_number}";
                    $htmlMessage = "<p>Multiple advance requests for Purchase Order #{$po->po_number}, associated with project {$project->name}, have been <strong>approved</strong>.</p>";
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                    }
                    break;

                default:
                    return;
            }

            $this->create(
                type: 'payment_approval',
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'po_id' => $po->id,
                    'po_number' => $po->po_number,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'status' => $status,
                    'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
                    'approved_by' => $approvedBy,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: $po->id,
                relatedType: 'PurchaseOrder',
                actionUrl: route('purchase-order.show', $po->id, false)
            );
        } else {
            // Invoice payment context
            $invoice = $paymentRequest->invoice;
            if (!$invoice) {
                return;
            }

            switch ($status) {
                case 'approved':
                    $title = "Payment Request Approved – {$invoice->invoice_number}";
                    $htmlMessage = "<p>The payment request for Purchase Invoice #{$invoice->invoice_number}, associated with project {$project->name}, has been <strong>approved</strong>.</p>";
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                    }
                    break;

                case 'partially_approved':
                    $title = "Payment Request Partially Approved – {$invoice->invoice_number}";
                    $htmlMessage = "<p>The payment request for Purchase Invoice #{$invoice->invoice_number}, associated with project {$project->name}, has been <strong>partially approved</strong>.</p>";
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                    }
                    break;

                case 'rejected':
                    $title = "Payment Request Rejected – {$invoice->invoice_number}";
                    $htmlMessage = "<p>The payment request for Purchase Invoice #{$invoice->invoice_number}, associated with project {$project->name}, has been <strong>rejected</strong>.</p>";
                    if ($rejectionReason) {
                        $htmlMessage .= "<p>Reason for rejection: {$rejectionReason}</p>";
                    }
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Rejected by: {$approvedBy}</p>";
                    }
                    break;

                case 'batch_approved':
                    $title = "Batch Payment Approval – {$invoice->invoice_number}";
                    $htmlMessage = "<p>Multiple payment requests for Purchase Invoice #{$invoice->invoice_number}, associated with project {$project->name}, have been <strong>approved</strong>.</p>";
                    if ($approvedBy) {
                        $htmlMessage .= "<p>Approved by: {$approvedBy}</p>";
                    }
                    break;

                default:
                    return;
            }

            $this->create(
                type: 'payment_approval',
                title: $title,
                message: $htmlMessage,
                messageArr: [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'status' => $status,
                    'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
                    'approved_by' => $approvedBy,
                ],
                userIds: $userIds,
                workspaceId: $project->workspace ?? null,
                projectId: $project->id,
                iconType: 'info',
                relatedId: $invoice->id,
                relatedType: 'PurchaseInvoice',
                actionUrl: route('purchase-invoice.show', $invoice->id, false)
            );
        }
    }

    public function createAnnouncementNotificationInstant(
    int $announcementId,
    int $projectId,
    string $title,
    string $description,
    string $createdBy
): void {
    try {
        Log::info('Announcement Notification Triggered', [
            'announcement_id' => $announcementId,
            'project_id'      => $projectId
        ]);

        // Get project details
        $project = DB::table('projects')->find($projectId);

        // ✅ Get all employees linked to this announcement
        $announcementEmployees = AnnouncementEmployee::where('announcement_id', $announcementId)
            ->pluck('employee_id')
            ->unique()
            ->toArray();

        Log::info('Employees for announcement', [
            'announcement_id' => $announcementId,
            'employee_ids'    => $announcementEmployees
        ]);

        // ✅ Delete existing notifications + user mappings
        $existingNotifications = ChNotification::where('type', 'announcement_created')
            ->where('related_id', $announcementId)
            ->where('related_type', 'Announcement')
            ->where('project_id', $projectId)
            ->get();

        foreach ($existingNotifications as $existing) {
            ChNotificationUser::where('notification_id', $existing->id)->delete();
            $existing->delete();
        }

        Log::info('Existing announcement notifications deleted', [
            'announcement_id' => $announcementId,
            'project_id'      => $projectId
        ]);

        // ✅ Build HTML message
        $htmlMessage = "<p>A new announcement has been created for project {$project->name}.</p>";
        $htmlMessage .= "<p>Title: {$title}</p>";
        $htmlMessage .= "<p>Description: {$description}</p>";
        $htmlMessage .= "<p>Created By: {$createdBy}</p>";

        // ✅ Create new notification
        $notification = $this->create(
            type: 'announcement_created',
            title: "New Announcement – {$project->name}",
            message: $htmlMessage,
            messageArr: [
                'announcement_id' => $announcementId,
                'project_id'      => $project->id,
                'project_name'    => $project->name,
                'title'           => $title,
                'description'     => $description,
                'created_by'      => $createdBy,
            ],
            userIds: $announcementEmployees,   // 👈 employees linked to announcement
            workspaceId: $project->workspace ?? null,
            projectId: $project->id,
            iconType: 'info',
            relatedId: $announcementId,
            relatedType: 'Announcement',
            actionUrl: route('announcement.index', [], false)
        );

        Log::info('Announcement notification created', ['id' => $notification->id]);
    } catch (\Exception $e) {
        Log::error('Announcement notification insert failed: ' . $e->getMessage());
    }
}

    // ==================== INDENT NOTIFICATIONS ====================

    public function createIndentCreatedNotification(Indent $indent): void
    {
        $project = DB::table('projects')->find($indent->site_id);
        $workspace = $indent->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveIndentIdentifier($indent);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'created',
            user: $user
        );

        $this->send(
            type: self::TYPE_INDENT_CREATED,
            title: "New Indent Created – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $indent->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'indent',
                'record_id' => $indent->id,
                'action' => 'created',
                'performed_by' => $indent->created_by,
                'indent_number' => $indent->indent_number,
                'indent_date' => $indent->indent_date,
            ]),
            workspaceId: $indent->workspace_id,
            projectId: $indent->site_id,
            iconType: 'success',
            relatedId: $indent->id,
            relatedType: 'Indent',
            actionUrl: route('indent.show', $indent->id, false),
            event: 'indent.created',
            model: $indent
        );
    }

    public function createIndentUpdatedNotification(Indent $indent, string $event = 'indent.updated'): void
    {
        $project = DB::table('projects')->find($indent->site_id);
        $workspace = $indent->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveIndentIdentifier($indent);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'updated',
            user: $user
        );

        $this->send(
            type: self::TYPE_INDENT_UPDATED,
            title: "Indent Updated – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $indent->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'indent',
                'record_id' => $indent->id,
                'action' => 'updated',
                'performed_by' => auth()->id(),
                'indent_number' => $indent->indent_number,
            ]),
            workspaceId: $indent->workspace_id,
            projectId: $indent->site_id,
            iconType: 'info',
            relatedId: $indent->id,
            relatedType: 'Indent',
            actionUrl: route('indent.show', $indent->id, false),
            event: $event,
            model: $indent
        );
    }

    public function createIndentStatusChangedNotification(Indent $indent, string $oldStatus, string $newStatus, string $event = 'indent.status_changed'): void
    {
        $project = DB::table('projects')->find($indent->site_id);
        $workspace = $indent->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveIndentIdentifier($indent);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'status_changed',
            user: $user,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->send(
            type: self::TYPE_INDENT_STATUS_CHANGED,
            title: "Indent Status Changed – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $indent->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'indent',
                'record_id' => $indent->id,
                'action' => 'status_changed',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'performed_by' => auth()->id(),
                'indent_number' => $indent->indent_number,
            ]),
            workspaceId: $indent->workspace_id,
            projectId: $indent->site_id,
            iconType: 'info',
            relatedId: $indent->id,
            relatedType: 'Indent',
            actionUrl: route('indent.show', $indent->id, false),
            event: $event,
            model: $indent
        );
    }

    // ==================== PURCHASE ORDER NOTIFICATIONS ====================

    public function createPOCreatedNotification(PurchaseOrder $po): void
    {
        $project = DB::table('projects')->find($po->site_id);
        $workspace = $po->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolvePOIdentifier($po);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'created',
            user: $user
        );

        $this->send(
            type: self::TYPE_PO_CREATED,
            title: "New Purchase Order Created – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $po->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'purchase_order',
                'record_id' => $po->id,
                'action' => 'created',
                'performed_by' => $po->created_by,
                'po_number' => $po->po_number,
                'po_date' => $po->po_date,
            ]),
            workspaceId: $po->workspace_id,
            projectId: $po->site_id,
            iconType: 'success',
            relatedId: $po->id,
            relatedType: 'PurchaseOrder',
            actionUrl: route('purchase-order.show', $po->id),
            event: 'po.created',
            model: $po
        );
    }

    public function createPOUpdatedNotification(PurchaseOrder $po, string $event = 'po.updated'): void
    {
        $project = DB::table('projects')->find($po->site_id);
        $workspace = $po->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolvePOIdentifier($po);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'updated',
            user: $user
        );

        $this->send(
            type: self::TYPE_PO_UPDATED,
            title: "Purchase Order Updated – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $po->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'purchase_order',
                'record_id' => $po->id,
                'action' => 'updated',
                'performed_by' => auth()->id(),
                'po_number' => $po->po_number,
            ]),
            workspaceId: $po->workspace_id,
            projectId: $po->site_id,
            iconType: 'info',
            relatedId: $po->id,
            relatedType: 'PurchaseOrder',
            actionUrl: route('purchase-order.show', $po->id),
            event: $event,
            model: $po
        );
    }

    public function createPOStatusChangedNotification(PurchaseOrder $po, string $oldStatus, string $newStatus, string $event = 'po.status_changed'): void
    {
        $project = DB::table('projects')->find($po->site_id);
        $workspace = $po->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolvePOIdentifier($po);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'status_changed',
            user: $user,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->send(
            type: self::TYPE_PO_STATUS_CHANGED,
            title: "Purchase Order Status Changed – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $po->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'purchase_order',
                'record_id' => $po->id,
                'action' => 'status_changed',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'performed_by' => auth()->id(),
                'po_number' => $po->po_number,
            ]),
            workspaceId: $po->workspace_id,
            projectId: $po->site_id,
            iconType: 'info',
            relatedId: $po->id,
            relatedType: 'PurchaseOrder',
            actionUrl: route('purchase-order.show', $po->id),
            event: $event,
            model: $po
        );
    }

    // ==================== GRN NOTIFICATIONS ====================

    public function createGrnCreatedNotification(Grn $grn): void
    {
        $project = DB::table('projects')->find($grn->site_id);
        $workspace = $grn->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveGRNIdentifier($grn);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'created',
            user: $user
        );

        $this->send(
            type: self::TYPE_GRN_CREATED,
            title: "New GRN Created – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $grn->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'grn',
                'record_id' => $grn->id,
                'action' => 'created',
                'performed_by' => $grn->created_by,
                'grn_number' => $grn->grn_number,
                'grn_date' => $grn->grn_date,
                'grn_type' => $grn->grn_type,
            ]),
            workspaceId: $grn->workspace_id,
            projectId: $grn->site_id,
            iconType: 'success',
            relatedId: $grn->id,
            relatedType: 'Grn',
            actionUrl: route('grn.show', $grn->id, false),
            event: 'grn.created',
            model: $grn
        );
    }

    public function createGrnUpdatedNotification(Grn $grn, string $event = 'grn.updated'): void
    {
        $project = DB::table('projects')->find($grn->site_id);
        $workspace = $grn->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveGRNIdentifier($grn);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'updated',
            user: $user
        );

        $this->send(
            type: self::TYPE_GRN_UPDATED,
            title: "GRN Updated – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $grn->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'grn',
                'record_id' => $grn->id,
                'action' => 'updated',
                'performed_by' => auth()->id(),
                'grn_number' => $grn->grn_number,
            ]),
            workspaceId: $grn->workspace_id,
            projectId: $grn->site_id,
            iconType: 'info',
            relatedId: $grn->id,
            relatedType: 'Grn',
            actionUrl: route('grn.show', $grn->id, false),
            event: $event,
            model: $grn
        );
    }

    public function createGrnStatusChangedNotification(Grn $grn, string $oldStatus, string $newStatus, string $event = 'grn.status_changed'): void
    {
        $project = DB::table('projects')->find($grn->site_id);
        $workspace = $grn->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveGRNIdentifier($grn);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'status_changed',
            user: $user,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->send(
            type: self::TYPE_GRN_STATUS_CHANGED,
            title: "GRN Status Changed – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $grn->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'grn',
                'record_id' => $grn->id,
                'action' => 'status_changed',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'performed_by' => auth()->id(),
                'grn_number' => $grn->grn_number,
            ]),
            workspaceId: $grn->workspace_id,
            projectId: $grn->site_id,
            iconType: 'info',
            relatedId: $grn->id,
            relatedType: 'Grn',
            actionUrl: route('grn.show', $grn->id, false),
            event: $event,
            model: $grn
        );
    }

    // ==================== PURCHASE INVOICE NOTIFICATIONS ====================

    public function createInvoiceCreatedNotification(PurchaseInvoice $invoice): void
    {
        $project = DB::table('projects')->find($invoice->site_id);
        $workspace = $invoice->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveInvoiceIdentifier($invoice);

        Log::info('Invoice notification - Identifier resolved', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'resolved_identifier' => $recordIdentifier,
        ]);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'created',
            user: $user
        );

        $this->send(
            type: self::TYPE_INVOICE_CREATED,
            title: "New Purchase Invoice Created – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $invoice->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'purchase_invoice',
                'record_id' => $invoice->id,
                'action' => 'created',
                'performed_by' => $invoice->created_by,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'invoice_type' => $invoice->invoice_type,
            ]),
            workspaceId: $invoice->workspace_id,
            projectId: $invoice->site_id,
            iconType: 'success',
            relatedId: $invoice->id,
            relatedType: 'PurchaseInvoice',
            actionUrl: route('purchase-invoice.show', $invoice->id),
            event: 'invoice.created',
            model: $invoice
        );
    }

    public function createInvoiceUpdatedNotification(PurchaseInvoice $invoice, string $event = 'invoice.updated'): void
    {
        $project = DB::table('projects')->find($invoice->site_id);
        $workspace = $invoice->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveInvoiceIdentifier($invoice);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'updated',
            user: $user
        );

        $this->send(
            type: self::TYPE_INVOICE_UPDATED,
            title: "Purchase Invoice Updated – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $invoice->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'purchase_invoice',
                'record_id' => $invoice->id,
                'action' => 'updated',
                'performed_by' => auth()->id(),
                'invoice_number' => $invoice->invoice_number,
            ]),
            workspaceId: $invoice->workspace_id,
            projectId: $invoice->site_id,
            iconType: 'info',
            relatedId: $invoice->id,
            relatedType: 'PurchaseInvoice',
            actionUrl: route('purchase-invoice.show', $invoice->id),
            event: $event,
            model: $invoice
        );
    }

    public function createInvoiceStatusChangedNotification(PurchaseInvoice $invoice, string $oldStatus, string $newStatus, string $event = 'invoice.status_changed'): void
    {
        $project = DB::table('projects')->find($invoice->site_id);
        $workspace = $invoice->workspace;
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspace->name ?? null;
        $user = auth()->user()?->name ?? 'System';

        // Safe fallback: always ensure recordIdentifier is a non-null string
        $recordIdentifier = $this->resolveInvoiceIdentifier($invoice);

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'status_changed',
            user: $user,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->send(
            type: self::TYPE_INVOICE_STATUS_CHANGED,
            title: "Purchase Invoice Status Changed – {$recordIdentifier}",
            message: $standardized['message'],
            siteId: $invoice->site_id,
            meta: array_merge($standardized['meta'], [
                'module' => 'purchase_invoice',
                'record_id' => $invoice->id,
                'action' => 'status_changed',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'performed_by' => auth()->id(),
                'invoice_number' => $invoice->invoice_number,
            ]),
            workspaceId: $invoice->workspace_id,
            projectId: $invoice->site_id,
            iconType: 'info',
            relatedId: $invoice->id,
            relatedType: 'PurchaseInvoice',
            actionUrl: route('purchase-invoice.show', $invoice->id),
            event: $event,
            model: $invoice
        );
    }

    // ==================== PAYMENT REQUEST NOTIFICATIONS ====================

    public function createPaymentRequestCreatedNotification(PaymentRequest $paymentRequest): void
    {
        $siteId = null;
        $workspaceId = null;
        $projectId = null;

        if ($paymentRequest->po_id && $paymentRequest->po) {
            $siteId = $paymentRequest->po->site_id;
            $workspaceId = $paymentRequest->po->workspace_id;
            $projectId = $paymentRequest->po->site_id;
        } elseif ($paymentRequest->purchase_invoice_id && $paymentRequest->invoice) {
            $siteId = $paymentRequest->invoice->site_id;
            $workspaceId = $paymentRequest->invoice->workspace_id;
            $projectId = $paymentRequest->invoice->site_id;
        }

        if (!$siteId) {
            return;
        }

        $project = DB::table('projects')->find($siteId);
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspaceId ? DB::table('work_spaces')->find($workspaceId)?->name : null;
        $user = auth()->user()?->name ?? 'System';
        $recordIdentifier = "PR-{$paymentRequest->id}";

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'created',
            user: $user
        );

        $this->send(
            type: self::TYPE_PAYMENT_REQUEST_CREATED,
            title: "New Payment Request Created – #{$paymentRequest->id}",
            message: $standardized['message'],
            siteId: $siteId,
            meta: array_merge($standardized['meta'], [
                'module' => 'payment_request',
                'record_id' => $paymentRequest->id,
                'action' => 'created',
                'performed_by' => $paymentRequest->requested_by,
                'type' => $paymentRequest->type,
                'requested_amount' => $paymentRequest->requested_amount,
            ]),
            workspaceId: $workspaceId,
            projectId: $projectId,
            iconType: 'success',
            relatedId: $paymentRequest->id,
            relatedType: 'PaymentRequest',
            actionUrl: route('payment-request.show', $paymentRequest->id, false),
            event: 'payment_request.created',
            model: $paymentRequest
        );
    }

    public function createPaymentRequestUpdatedNotification(PaymentRequest $paymentRequest, string $event = 'payment_request.updated'): void
    {
        $siteId = null;
        $workspaceId = null;
        $projectId = null;

        if ($paymentRequest->po_id && $paymentRequest->po) {
            $siteId = $paymentRequest->po->site_id;
            $workspaceId = $paymentRequest->po->workspace_id;
            $projectId = $paymentRequest->po->site_id;
        } elseif ($paymentRequest->purchase_invoice_id && $paymentRequest->invoice) {
            $siteId = $paymentRequest->invoice->site_id;
            $workspaceId = $paymentRequest->invoice->workspace_id;
            $projectId = $paymentRequest->invoice->site_id;
        }

        if (!$siteId) {
            return;
        }

        $project = DB::table('projects')->find($siteId);
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspaceId ? DB::table('work_spaces')->find($workspaceId)?->name : null;
        $user = auth()->user()?->name ?? 'System';
        $recordIdentifier = "PR-{$paymentRequest->id}";

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'updated',
            user: $user
        );

        $this->send(
            type: self::TYPE_PAYMENT_REQUEST_UPDATED,
            title: "Payment Request Updated – #{$paymentRequest->id}",
            message: $standardized['message'],
            siteId: $siteId,
            meta: array_merge($standardized['meta'], [
                'module' => 'payment_request',
                'record_id' => $paymentRequest->id,
                'action' => 'updated',
                'performed_by' => auth()->id(),
            ]),
            workspaceId: $workspaceId,
            projectId: $projectId,
            iconType: 'info',
            relatedId: $paymentRequest->id,
            relatedType: 'PaymentRequest',
            actionUrl: route('payment-request.show', $paymentRequest->id, false),
            event: $event,
            model: $paymentRequest
        );
    }

    public function createPaymentRequestStatusChangedNotification(PaymentRequest $paymentRequest, string $oldStatus, string $newStatus, string $event = 'payment_request.status_changed'): void
    {
        $siteId = null;
        $workspaceId = null;
        $projectId = null;

        if ($paymentRequest->po_id && $paymentRequest->po) {
            $siteId = $paymentRequest->po->site_id;
            $workspaceId = $paymentRequest->po->workspace_id;
            $projectId = $paymentRequest->po->site_id;
        } elseif ($paymentRequest->purchase_invoice_id && $paymentRequest->invoice) {
            $siteId = $paymentRequest->invoice->site_id;
            $workspaceId = $paymentRequest->invoice->workspace_id;
            $projectId = $paymentRequest->invoice->site_id;
        }

        if (!$siteId) {
            return;
        }

        $project = DB::table('projects')->find($siteId);
        $siteName = $project->name ?? 'Unknown Site';
        $workspaceName = $workspaceId ? DB::table('work_spaces')->find($workspaceId)?->name : null;
        $user = auth()->user()?->name ?? 'System';
        $recordIdentifier = "PR-{$paymentRequest->id}";

        $standardized = $this->formatStandardMessage(
            siteName: $siteName,
            workspaceName: $workspaceName,
            recordIdentifier: $recordIdentifier,
            action: 'status_changed',
            user: $user,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->send(
            type: self::TYPE_PAYMENT_REQUEST_STATUS_CHANGED,
            title: "Payment Request Status Changed – #{$paymentRequest->id}",
            message: $standardized['message'],
            siteId: $siteId,
            meta: array_merge($standardized['meta'], [
                'module' => 'payment_request',
                'record_id' => $paymentRequest->id,
                'action' => 'status_changed',
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'performed_by' => auth()->id(),
            ]),
            workspaceId: $workspaceId,
            projectId: $projectId,
            iconType: 'info',
            relatedId: $paymentRequest->id,
            relatedType: 'PaymentRequest',
            actionUrl: route('payment-request.show', $paymentRequest->id, false),
            event: $event,
            model: $paymentRequest
        );
    }

}
