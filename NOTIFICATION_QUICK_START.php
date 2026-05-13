<?php

/**
 * QUICK START: How to Send Notifications from Your Code
 *
 * This file contains example code for triggering notifications
 * from various parts of your SitePilot application.
 */

// ============================================================================
// 1. SENDING LOW STOCK NOTIFICATIONS
// ============================================================================

// In your Material model or controller:
use App\Services\NotificationService;

// When updating material quantity
public function updateStock($materialId, $newQuantity)
{
    $material = Material::find($materialId);
    $material->quantity = $newQuantity;
    $material->save();

    // Check if below reorder level and send notification
    if ($newQuantity <= $material->reorder_level) {
        app(NotificationService::class)->createLowStockNotification(
            materialId: $material->id,
            materialName: $material->name,
            userIds: $this->getWarehouseManagerIds($material->workspace_id),
            projectId: $material->project_id,
            workspaceId: $material->workspace_id
        );
    }
}

// ============================================================================
// 2. SENDING ANNOUNCEMENT NOTIFICATIONS
// ============================================================================

// In your Announcement controller (after creating/updating announcement):
use App\Jobs\SendAnnouncementNotification;

public function store(Request $request)
{
    $announcement = Announcement::create($request->validated());

    // Dispatch job to send notifications
    dispatch(new SendAnnouncementNotification($announcement));

    return response()->json(['success' => true, 'announcement' => $announcement]);
}

// ============================================================================
// 3. GETTING NOTIFICATIONS IN VIEWS
// ============================================================================

// In your Blade template (like header.blade.php):
@php
    $notificationService = app(\App\Services\NotificationService::class);
    $unreadNotifications = $notificationService->getUnreadNotifications(Auth::id(), 10);
    $unreadCount = $notificationService->countUnreadNotifications(Auth::id());
@endphp

<span class="badge">{{ $unreadCount }}</span>

// ============================================================================
// 4. MANUAL NOTIFICATION CREATION
// ============================================================================

use App\Services\NotificationService;

// Generic notification creation
app(NotificationService::class)->create(
    type: 'custom_type',
    title: 'Notification Title',
    message: 'Notification message here',
    userIds: [1, 2, 3],
    workspaceId: 1,
    projectId: 5,
    iconType: 'info', // 'info', 'success', 'warning', 'error'
    relatedId: 123,
    relatedType: 'CustomType',
    actionUrl: '/path/to/resource'
);

// ============================================================================
// 5. USING IN ARTISAN COMMANDS
// ============================================================================

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendCustomNotification extends Command
{
    protected $signature = 'notification:send-custom {type} {title} {message}';

    public function handle(NotificationService $notificationService)
    {
        $notificationService->create(
            type: $this->argument('type'),
            title: $this->argument('title'),
            message: $this->argument('message'),
            userIds: [1, 2], // Specify users
            iconType: 'info'
        );

        $this->info('Notification sent successfully!');
    }
}

// Run: php artisan notification:send-custom low_stock "Stock Low" "Cement is running low"

// ============================================================================
// 6. USING IN EVENTS (WHEN SOMETHING HAPPENS)
// ============================================================================

namespace App\Models;

use App\Services\NotificationService;

class Material extends Model
{
    protected static function boot()
    {
        parent::boot();

        // When material is created
        static::created(function ($material) {
            if ($material->quantity <= $material->reorder_level) {
                app(NotificationService::class)->createLowStockNotification(
                    materialId: $material->id,
                    materialName: $material->name,
                    userIds: [1, 2, 3],
                    workspaceId: $material->workspace_id
                );
            }
        });
    }
}

// ============================================================================
// 7. GETTING NOTIFICATIONS WITH FILTERS
// ============================================================================

use App\Models\ChNotificationUser;

// Get unread notifications for specific user
$unreadNotifs = ChNotificationUser::where('user_id', Auth::id())
    ->whereNull('read_at')
    ->with('notification')
    ->latest('created_at')
    ->get();

// Get specific type of notifications
$lowStockNotifs = ChNotificationUser::whereHas('notification', function ($q) {
    $q->where('type', 'low_stock');
})
->where('user_id', Auth::id())
->get();

// ============================================================================
// 8. MARKING NOTIFICATIONS AS READ (in API/Controller)
// ============================================================================

public function markAsRead(Request $request)
{
    $notificationService = app(NotificationService::class);

    $notificationService->markAsRead(
        userNotificationId: $request->notification_id,
        userId: Auth::id()
    );

    return response()->json([
        'success' => true,
        'unread_count' => $notificationService->countUnreadNotifications(Auth::id())
    ]);
}

// ============================================================================
// 9. SENDING FCM NOTIFICATIONS MANUALLY
// ============================================================================

use App\Services\NotificationService;

$notificationService = app(NotificationService::class);
$notification = Notification::find(1);
$users = User::whereIn('id', [1, 2, 3])->get();

// Send FCM to specific users
$notificationService->sendFCMNotifications($notification, $users);

// ============================================================================
// 10. EXAMPLE: CUSTOM NOTIFICATION WITH EVENT LISTENER
// ============================================================================

namespace App\Listeners;

use App\Events\LowStockEvent;
use App\Services\NotificationService;

class SendLowStockNotification
{
    public function handle(LowStockEvent $event)
    {
        app(NotificationService::class)->createLowStockNotification(
            materialId: $event->material->id,
            materialName: $event->material->name,
            userIds: $event->notifiableUserIds,
            workspaceId: $event->material->workspace_id
        );
    }
}

// Dispatch event from model:
Material::created(function ($material) {
    if ($material->quantity <= $material->reorder_level) {
        event(new LowStockEvent($material, [1, 2, 3]));
    }
});

// ============================================================================
// 11. API USAGE FROM FRONTEND (JavaScript)
// ============================================================================

/*
// Get unread notifications
fetch('/api/notifications/unread?limit=10', {
    method: 'GET',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
    }
})
.then(response => response.json())
.then(data => console.log(data.notifications));

// Mark as read
fetch('/api/notifications/mark-as-read', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ notification_id: 5 })
})
.then(response => response.json())
.then(data => console.log('Marked as read'));

// Get count
fetch('/api/notifications/count', {
    method: 'GET',
    headers: {
        'Authorization': `Bearer ${token}`
    }
})
.then(response => response.json())
.then(data => console.log('Unread count:', data.unread_count));
*/

// ============================================================================
// HELPFUL TIPS
// ============================================================================

/*
1. Always wrap notification creation in try-catch if calling from critical paths
2. Use queued jobs for bulk notifications to avoid blocking
3. Set appropriate userIds - use role-based filtering
4. Test notifications with php artisan tinker before production
5. Monitor notification queue: php artisan queue:work --queue=notifications
6. Check logs for FCM failures: tail -f storage/logs/laravel.log
7. Use iconType: 'warning' for urgent notifications like low stock
8. Use iconType: 'success' for celebratory events like birthdays
*/
