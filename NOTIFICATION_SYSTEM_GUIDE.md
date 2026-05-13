# Real-Time Notifications System - Integration Guide

This document provides a complete guide to implementing the real-time notifications system for SitePilot.

## Overview

The notification system includes:
- **Low Stock Alerts** - Monitor materials below reorder level
- **Birthday Reminders** - Notify on employee birthdays
- **Announcements** - Broadcast announcements to users
- **Holiday Notifications** - Remind about upcoming holidays
- **Event Notifications** - Alert about upcoming events
- **FCM Integration** - Push notifications via Firebase Cloud Messaging

## Database Setup

### Migrations to Run

Two new tables have been created:

1. **`ch_notifications`** - Stores notification details
2. **`ch_notification_users`** - Tracks user-notification associations and read status

**Run migrations:**
```bash
php artisan migrate
```

## File Structure

### New Files Created

```
app/
├── Models/
│   ├── ChNotification.php          # Notification model
│   └── ChNotificationUser.php      # User notification mapping
├── Services/
│   └── NotificationService.php     # Core notification service
├── Http/Controllers/Api/
│   └── NotificationController.php  # API endpoints
├── Jobs/
│   ├── CheckLowStockNotification.php
│   ├── CheckBirthdayNotification.php
│   ├── SendAnnouncementNotification.php
│   ├── CheckHolidayNotification.php
│   └── CheckEventNotification.php
└── Console/
    └── Kernel.php                  # Scheduled jobs

database/migrations/
├── 2026_01_10_000001_create_ch_notifications_table.php
└── 2026_01_10_000002_create_ch_notification_users_table.php

resources/views/partials/
└── header.blade.php                # Updated with real notifications
```

### Updated Files

- `routes/api.php` - Added notification API routes
- `resources/views/partials/header.blade.php` - Integrated real notifications display

## API Endpoints

All endpoints require authentication and are prefixed with `/api/notifications`:

### Get Unread Notifications
```http
GET /api/notifications/unread?limit=10
```
**Response:**
```json
{
  "success": true,
  "notifications": [
    {
      "id": 1,
      "user_notif_id": 5,
      "type": "low_stock",
      "title": "Low Stock Alert",
      "message": "Material 'Cement' has reached its reorder level.",
      "icon_type": "warning",
      "action_url": "/material/1",
      "time": "2 minutes ago",
      "read": false
    }
  ],
  "unread_count": 3
}
```

### Get All Notifications
```http
GET /api/notifications/all?limit=20&offset=0
```

### Get Unread Count
```http
GET /api/notifications/count
```
**Response:**
```json
{
  "success": true,
  "unread_count": 5
}
```

### Mark Notification as Read
```http
POST /api/notifications/mark-as-read
```
**Payload:**
```json
{
  "notification_id": 5
}
```

### Mark All as Read
```http
POST /api/notifications/mark-all-as-read
```

### Delete Notification
```http
POST /api/notifications/delete
```
**Payload:**
```json
{
  "notification_id": 5
}
```

### Delete All Notifications
```http
POST /api/notifications/delete-all
```

## Usage Examples

### Creating a Low Stock Notification

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

$notificationService->createLowStockNotification(
    materialId: 123,
    materialName: 'Cement',
    userIds: [1, 2, 3],
    projectId: 5,
    workspaceId: 1
);
```

### Creating a Birthday Notification

```php
$notificationService->createBirthdayNotification(
    employeeId: 45,
    employeeName: 'John Doe',
    userIds: [1, 2, 3, 4],
    projectId: null,
    workspaceId: 1
);
```

### Creating an Announcement

```php
use App\Jobs\SendAnnouncementNotification;
use App\Models\Announcement;

$announcement = Announcement::find(1);
dispatch(new SendAnnouncementNotification($announcement));
```

### Getting Unread Notifications for a User

```php
$unreadNotifications = $notificationService->getUnreadNotifications(
    userId: Auth::id(),
    limit: 10
);
```

## Scheduled Jobs

Jobs are configured to run on the following schedule:

| Job | Schedule | Time |
|-----|----------|------|
| Low Stock Check | Hourly | Every hour |
| Birthday Check | Daily | 8:00 AM |
| Holiday Check | Daily | 9:00 AM |
| Event Check | Daily | 10:00 AM |

**To start the scheduler:**
```bash
php artisan schedule:work
```

Or add to cron (production):
```bash
* * * * * cd /path/to/sitepilot && php artisan schedule:run >> /dev/null 2>&1
```

## FCM Integration

### 1. Setup Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create a new project or use existing one
3. Add a Web app
4. Copy the configuration

### 2. Add FCM Meta Tags to Layout

Add to your main layout (before closing `</head>`):

```html
<meta name="fcm-vapid-key" content="YOUR_VAPID_KEY">
```

### 3. Create Service Worker

Create `public/firebase-messaging-sw.js`:

```javascript
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging.js');

const firebaseConfig = {
    apiKey: "YOUR_API_KEY",
    authDomain: "YOUR_AUTH_DOMAIN",
    projectId: "YOUR_PROJECT_ID",
    storageBucket: "YOUR_STORAGE_BUCKET",
    messagingSenderId: "YOUR_MESSAGING_SENDER_ID",
    appId: "YOUR_APP_ID"
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    console.log('Received background message:', payload);
    self.registration.showNotification(payload.notification.title, {
        body: payload.notification.body,
        icon: payload.notification.icon,
    });
});
```

### 4. Initialize FCM in Frontend

The header.blade.php already includes FCM initialization. Make sure to:

1. Include Firebase in your main layout:
```html
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging.js"></script>
```

2. Initialize Firebase with your config

## Database Relationships

### ChNotification Model

```php
// Get all users notified
$notification->users();

// Get all user-notification records
$notification->userNotifications();

// Get workspace
$notification->workspace();

// Get project
$notification->project();
```

### ChNotificationUser Model

```php
// Get the notification
$userNotif->notification();

// Get the user
$userNotif->user();

// Check if read
$userNotif->isRead();

// Mark as read
$userNotif->markAsRead();
```

## Frontend Integration

The notification system is already integrated in `header.blade.php`. It includes:

1. **Real-time UI Updates** - Fetches notifications from API
2. **Auto-refresh** - Refreshes every 30 seconds when dropdown is open
3. **Mark as Read** - Individual and bulk marking
4. **FCM Support** - Receives push notifications
5. **Action URLs** - Navigate to related resources on click

### Customizing Notification Icons

Edit the `getNotificationIcon()` function in the script section to customize icons for different notification types.

## Testing

### Test Low Stock Notification

```php
// Artisan command
php artisan tinker

$material = Material::find(1);
$material->quantity = 5;
$material->reorder_level = 10;
$material->save();

dispatch(new CheckLowStockNotification());
```

### Test Birthday Notification

```php
$employee = Employee::find(1);
$employee->dob = now()->format('Y-m-d');
$employee->save();

dispatch(new CheckBirthdayNotification());
```

### Manual API Test

```bash
curl -X GET http://localhost/api/notifications/unread \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## Configuration

### Modify Notification Recipients

Edit the `getNotifiableUsers()` method in each job class to customize who receives notifications.

Example (Low Stock):
```php
private function getNotifiableUsers(Material $material): array
{
    // Customize who gets notified
    $userIds = \DB::table('model_has_roles')
        ->join('users', 'model_has_roles.model_id', '=', 'users.id')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_type', 'App\Models\User')
        ->whereIn('roles.name', ['admin', 'warehouse_manager'])
        ->pluck('users.id')
        ->toArray();
    
    return array_unique($userIds);
}
```

### Modify Schedule Times

Edit `app/Console/Kernel.php` to change when notifications are checked:

```php
// Change from 8:00 AM to 7:00 AM
$schedule->job(new CheckBirthdayNotification)
    ->dailyAt('07:00');

// Change to every 30 minutes
$schedule->job(new CheckLowStockNotification)
    ->everyThirtyMinutes();
```

## Troubleshooting

### Notifications Not Appearing

1. Check if migrations were run: `php artisan migrate:status`
2. Verify scheduler is running: `php artisan schedule:work`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Check browser console for JavaScript errors

### FCM Issues

1. Verify VAPID key is set correctly
2. Check Firebase configuration
3. Test service worker: DevTools > Application > Service Workers
4. Check browser notification permissions

### API Issues

1. Verify authentication token is valid
2. Check CSRF token in request headers
3. Verify notification_id exists before marking as read

## Additional Resources

- [Laravel Scheduling](https://laravel.com/docs/scheduling)
- [Laravel Jobs & Queues](https://laravel.com/docs/queues)
- [Firebase Cloud Messaging](https://firebase.google.com/docs/cloud-messaging)
- [Laravel Models](https://laravel.com/docs/eloquent)

## Next Steps

1. Run migrations
2. Configure FCM (if using push notifications)
3. Test notification creation through various scenarios
4. Customize notification recipients and schedules as needed
5. Monitor logs for any issues
