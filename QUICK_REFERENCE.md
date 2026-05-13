# 📋 Notifications System - Quick Reference Card

## 🚀 Quick Start Commands

```bash
# Run migrations
php artisan migrate

# Clear cache
php artisan cache:clear

# Start scheduler
php artisan schedule:work

# Start queue worker
php artisan queue:work --queue=notifications

# Test with Artisan
php artisan tinker
```

---

## 🔔 Create Notifications

### Using Helpers (Easiest)
```php
// Low Stock
notify_low_stock($materialId, $name, $userIds, $projectId, $workspaceId);

// Birthday
notify_birthday($empId, $name, $userIds, $projectId, $workspaceId);

// Announcement
notify_announcement($announcementId, $title, $userIds, $projectId, $workspaceId);

// Custom
notify($type, $title, $message, $userIds, $workspaceId, $projectId, $iconType, $relatedId, $relatedType, $actionUrl);
```

### Using Service
```php
app(NotificationService::class)->createLowStockNotification(
    materialId: 1,
    materialName: 'Cement',
    userIds: [1, 2],
    projectId: 5,
    workspaceId: 1
);
```

### Using Helper Class
```php
use App\Helpers\NotificationHelper;

NotificationHelper::notifyLowStock($id, $name, $users, $project, $workspace);
NotificationHelper::getWorkspaceUsers($workspaceId);
NotificationHelper::getUsersWithRole($workspaceId, 'admin');
```

---

## 📊 Get Notifications

```php
$service = app(NotificationService::class);

// Unread (max 10)
$service->getUnreadNotifications($userId, 10);

// All with pagination
$service->getAllNotifications($userId, 20, 0);

// Count unread
$service->countUnreadNotifications($userId);

// Query raw
ChNotificationUser::where('user_id', 1)->whereNull('read_at')->get();
```

---

## ✅ Manage Notifications

```php
$service = app(NotificationService::class);

// Mark as read
$service->markAsRead($userNotifId, $userId);

// Mark all as read
$service->markAllAsRead($userId);

// Delete
$service->deleteNotification($userNotifId, $userId);

// Delete all
$service->deleteAllNotifications($userId);
```

---

## 🌐 API Endpoints

```bash
# Get unread (limit=10)
GET /api/notifications/unread?limit=10

# Get all (limit=20, offset=0)
GET /api/notifications/all?limit=20&offset=0

# Get count
GET /api/notifications/count

# Mark as read
POST /api/notifications/mark-as-read
Body: { "notification_id": 5 }

# Mark all as read
POST /api/notifications/mark-all-as-read

# Delete
POST /api/notifications/delete
Body: { "notification_id": 5 }

# Delete all
POST /api/notifications/delete-all
```

---

## 🔄 Scheduled Jobs

| Time | Job |
|------|-----|
| **Hourly** | CheckLowStockNotification |
| **8:00 AM** | CheckBirthdayNotification |
| **9:00 AM** | CheckHolidayNotification |
| **10:00 AM** | CheckEventNotification |

---

## 📦 Model Relationships

```php
// ChNotification
$notif->users();              // Many users
$notif->userNotifications();  // User mappings
$notif->workspace();          // Workspace
$notif->project();            // Project

// ChNotificationUser
$userNotif->notification();   // The notification
$userNotif->user();           // The user
$userNotif->markAsRead();     // Mark as read
$userNotif->isRead();         // Check if read
```

---

## 🎨 Notification Types & Icons

| Type | Icon | Use Case |
|------|------|----------|
| `low_stock` | 🔴 alert-triangle | Material below reorder level |
| `birthday` | 🎂 cake | Employee birthday |
| `announcement` | 📢 bell-ringing | New announcement |
| `holiday` | 🎉 calendar-event | Upcoming holiday |
| `event` | 📅 calendar | Upcoming event |

---

## 🎯 Usage Patterns

### In Controller
```php
$service = app(NotificationService::class);
$service->createLowStockNotification($id, $name, $users, $proj, $ws);
```

### In Model Boot
```php
static::created(function ($model) {
    dispatch(new SendAnnouncementNotification($model));
});
```

### In Blade
```blade
{{ unread_count(Auth::id()) }}
@foreach(unread_notifications(Auth::id()) as $n)
    <div>{{ $n['title'] }}</div>
@endforeach
```

### In Artisan Command
```php
public function handle()
{
    notify('type', 'title', 'message', [1, 2, 3]);
}
```

---

## 📁 Files Created

```
app/
├── Console/Kernel.php
├── Helpers/NotificationHelper.php
├── Http/Controllers/Api/NotificationController.php
├── Jobs/CheckBirthdayNotification.php
├── Jobs/CheckEventNotification.php
├── Jobs/CheckHolidayNotification.php
├── Jobs/CheckLowStockNotification.php
├── Jobs/SendAnnouncementNotification.php
├── Models/ChNotification.php
├── Models/ChNotificationUser.php
└── Services/NotificationService.php

database/migrations/
├── 2026_01_10_000001_create_ch_notifications_table.php
└── 2026_01_10_000002_create_ch_notification_users_table.php
```

---

## 🧪 Testing

```bash
# In Artisan tinker
php artisan tinker

# Test notification
notify('test', 'Test', 'Message', [1]);

# Get count
unread_count(1);

# Get notifications
unread_notifications(1);

# Mark as read
ChNotificationUser::find(1)->markAsRead();

# Exit
exit
```

---

## 🔧 Customization

### Change Schedule Time
```php
// In app/Console/Kernel.php
$schedule->job(new CheckBirthdayNotification)
    ->dailyAt('07:00');  // Change from 8:00 AM to 7:00 AM
```

### Change Recipients
```php
// In each job's getNotifiableUsers()
return \DB::table('users')
    ->where('workspace_id', $wsId)
    ->whereIn('role', ['admin', 'manager'])
    ->pluck('id')
    ->toArray();
```

### Add Custom Icon
```javascript
// In header.blade.php script
function getNotificationIcon(type) {
    const icons = {
        'my_type': '<i class="ti ti-my-icon"></i>',
        // ... existing
    };
}
```

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| Notifications not showing | Check: migrations ran, user logged in, API token valid |
| API returns 401 | Verify: bearer token in header, user authenticated |
| Jobs not running | Start: `php artisan schedule:work` |
| FCM not working | Check: config, service worker, permissions, console |
| Database error | Run: `php artisan migrate` |

---

## 📚 Documentation Files

- **IMPLEMENTATION_SUMMARY.md** - System overview
- **NOTIFICATION_SYSTEM_GUIDE.md** - Complete guide
- **NOTIFICATION_QUICK_START.php** - Code examples
- **INTEGRATION_EXAMPLES.php** - Integration patterns
- **SETUP_CHECKLIST.md** - Setup steps

---

## ⚡ Performance Tips

```php
// Use eager loading
ChNotificationUser::with('notification')->get();

// Limit results
->limit(10);

// Index lookups
->whereNull('read_at')  // Uses index on read_at

// Batch operations
ChNotificationUser::where('user_id', 1)
    ->whereNull('read_at')
    ->update(['read_at' => now()]);
```

---

## 🔐 Security Notes

- All API endpoints require authentication
- CSRF token required for POST requests
- User can only see own notifications
- Bearer token for mobile/API clients

---

## 📞 Common Questions

**Q: How do I test notifications?**  
A: Use `php artisan tinker` and `notify()` function

**Q: How often do jobs run?**  
A: Hourly (low stock), Daily at 8/9/10 AM (others)

**Q: Can I change notification recipients?**  
A: Yes, edit `getNotifiableUsers()` in each job

**Q: How do I add FCM?**  
A: Set up Firebase, add config, include scripts

**Q: Can users customize notifications?**  
A: Yes, create preferences table and filter

---

**Quick Links:**
- 📖 [Laravel Docs](https://laravel.com)
- 🔥 [Firebase Docs](https://firebase.google.com/docs)
- 📊 [API Reference](#api-endpoints)

---

**Last Updated:** January 10, 2026
