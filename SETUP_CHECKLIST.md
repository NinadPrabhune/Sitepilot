# 🚀 Real-Time Notifications System - Setup Checklist

## ✅ Phase 1: Initial Setup (Do First)

- [ ] **Step 1: Run Database Migrations**
  ```bash
  php artisan migrate
  ```
  This creates the `ch_notifications` and `ch_notification_users` tables.

- [ ] **Step 2: Clear Cache and Reload Composer**
  ```bash
  php artisan cache:clear
  composer dump-autoload
  ```

- [ ] **Step 3: Verify Files are in Place**
  - [ ] Check `app/Models/ChNotification.php` exists
  - [ ] Check `app/Models/ChNotificationUser.php` exists
  - [ ] Check `app/Services/NotificationService.php` exists
  - [ ] Check `app/Http/Controllers/Api/NotificationController.php` exists
  - [ ] Check `app/Console/Kernel.php` exists
  - [ ] Check `app/Helpers/NotificationHelper.php` exists
  - [ ] Check `app/Jobs/*.php` (5 job files)

---

## ✅ Phase 2: Testing (Verify It Works)

- [ ] **Step 4: Start Laravel Scheduler**
  ```bash
  # In terminal 1
  php artisan schedule:work
  ```

- [ ] **Step 5: Start Queue Worker (Optional)**
  ```bash
  # In terminal 2
  php artisan queue:work --queue=notifications
  ```

- [ ] **Step 6: Test API Endpoints**
  ```bash
  # Get unread count
  curl -X GET http://localhost/api/notifications/count \
    -H "Authorization: Bearer YOUR_API_TOKEN" \
    -H "Accept: application/json"
  
  # Get unread notifications
  curl -X GET http://localhost/api/notifications/unread \
    -H "Authorization: Bearer YOUR_API_TOKEN" \
    -H "Accept: application/json"
  ```

- [ ] **Step 7: Test Notification Creation**
  ```bash
  php artisan tinker
  
  # In tinker shell:
  $service = app(\App\Services\NotificationService::class);
  $service->create('test', 'Test Notification', 'This is a test', [1]);
  exit
  ```

- [ ] **Step 8: Check Header Displays Notifications**
  - [ ] Log in to application
  - [ ] Look at top right header
  - [ ] Should see bell icon with count
  - [ ] Click bell to see notifications dropdown
  - [ ] Verify "Mark all as read" works

---

## ✅ Phase 3: Integration with Existing Features

### Material/Stock Management
- [ ] **Step 9: Integrate Low Stock Notifications**
  - [ ] In `app/Models/Material.php`, add boot method to check stock
  - [ ] Or in Material controller, trigger notification on update
  - [ ] Test: Update material to be below reorder level
  - [ ] Verify: Managers receive notification

### Announcements
- [ ] **Step 10: Integrate Announcement Notifications**
  - [ ] In `app/Models/Announcement.php`, add observer or boot method
  - [ ] Dispatch `SendAnnouncementNotification` on creation
  - [ ] Test: Create new announcement
  - [ ] Verify: All workspace users get notification

### Employees
- [ ] **Step 11: Test Birthday Notifications**
  - [ ] Update an employee's DOB to today's date
  - [ ] Wait for 8:00 AM or manually run:
    ```bash
    php artisan schedule:run
    ```
  - [ ] Verify: Notification appears for workspace users

### Holidays
- [ ] **Step 12: Test Holiday Notifications**
  - [ ] Create holiday with today's date
  - [ ] Run scheduler or wait for 9:00 AM
  - [ ] Verify: Notification appears

### Events
- [ ] **Step 13: Test Event Notifications**
  - [ ] Create event for today
  - [ ] Run scheduler or wait for 10:00 AM
  - [ ] Verify: Event attendees get notification

---

## ✅ Phase 4: FCM Setup (Optional - For Push Notifications)

- [ ] **Step 14: Setup Firebase Project**
  - [ ] Go to [Firebase Console](https://console.firebase.google.com/)
  - [ ] Create project or use existing
  - [ ] Add Web app
  - [ ] Copy configuration

- [ ] **Step 15: Configure FCM in .env**
  ```env
  FIREBASE_PROJECT_ID=your_project_id
  FIREBASE_PRIVATE_KEY_ID=your_private_key_id
  FIREBASE_PRIVATE_KEY=your_private_key
  FIREBASE_CLIENT_EMAIL=your_client_email
  ```

- [ ] **Step 16: Create Service Worker**
  - [ ] Create `public/firebase-messaging-sw.js`
  - [ ] Add Firebase initialization code

- [ ] **Step 17: Add Firebase to Layout**
  - [ ] Add Firebase scripts to main layout
  - [ ] Add FCM VAPID key to meta tag

- [ ] **Step 18: Test FCM**
  - [ ] Allow browser notifications
  - [ ] Create notification
  - [ ] Verify push notification appears

---

## ✅ Phase 5: Customization

- [ ] **Step 19: Customize Who Gets Notified**
  - [ ] Edit `getNotifiableUsers()` in each job class
  - [ ] Filter by role, workspace, project as needed
  - [ ] Example: Only notify warehouse managers for low stock

- [ ] **Step 20: Adjust Schedule Times**
  - [ ] Edit `app/Console/Kernel.php`
  - [ ] Change times to match your business hours
  - [ ] Current:
    - 8:00 AM - Birthday check
    - 9:00 AM - Holiday check
    - 10:00 AM - Event check
    - Every hour - Low stock check

- [ ] **Step 21: Customize Notification Icons**
  - [ ] Edit `getNotificationIcon()` in `header.blade.php`
  - [ ] Add more icon types
  - [ ] Test different icons

- [ ] **Step 22: Setup User Preferences**
  - [ ] Create `notification_preferences` table (optional)
  - [ ] Allow users to disable certain notification types
  - [ ] Filter notifications based on preferences

---

## ✅ Phase 6: Deployment

- [ ] **Step 23: Database Backups**
  - [ ] Backup existing database
  - [ ] Have rollback plan

- [ ] **Step 24: Run Migrations on Production**
  ```bash
  php artisan migrate --force
  ```

- [ ] **Step 25: Setup Scheduler in Cron**
  ```bash
  # Add to crontab
  * * * * * cd /path/to/sitepilot && php artisan schedule:run >> /dev/null 2>&1
  ```

- [ ] **Step 26: Setup Queue Worker (Production)**
  ```bash
  # Use Supervisor to manage queue:work process
  ```

- [ ] **Step 27: Test on Production**
  - [ ] Create test notification
  - [ ] Verify API endpoints work
  - [ ] Check header displays notifications

- [ ] **Step 28: Monitor Logs**
  ```bash
  tail -f storage/logs/laravel.log
  ```

---

## 📝 Documentation Files

| File | Purpose |
|------|---------|
| `IMPLEMENTATION_SUMMARY.md` | Overview of entire system |
| `NOTIFICATION_SYSTEM_GUIDE.md` | Complete integration guide |
| `NOTIFICATION_QUICK_START.php` | Code examples |
| `INTEGRATION_EXAMPLES.php` | Real-world patterns |
| `SETUP_CHECKLIST.md` | This file |

---

## 🔍 Quick Troubleshooting

### Notifications Not Showing
```bash
# Check if tables exist
php artisan tinker
ChNotification::count();
exit

# Check if migrations ran
php artisan migrate:status
```

### API Returns 401
- Verify bearer token is valid
- Check token in Authorization header
- Make sure user is authenticated

### Scheduler Not Running
```bash
# Start scheduler
php artisan schedule:work

# Or check if process is running
ps aux | grep "schedule:work"
```

### FCM Not Working
- Verify Firebase config in .env
- Check service worker in DevTools
- Verify permissions allowed in browser
- Check browser console for errors

---

## 💡 Common Tasks

### Create Test Notification
```php
php artisan tinker
notify('test', 'Test Title', 'Test message', [1]);
exit
```

### Get All Notifications for User
```php
php artisan tinker
$notifs = app(\App\Services\NotificationService::class)->getAllNotifications(1, 20);
exit
```

### Mark All as Read
```bash
curl -X POST http://localhost/api/notifications/mark-all-as-read \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### Get Unread Count
```bash
curl -X GET http://localhost/api/notifications/count \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📊 Database Verification

### Check Tables Created
```sql
SHOW TABLES LIKE 'ch_notification%';
```

### Check Notification Count
```sql
SELECT COUNT(*) FROM ch_notifications;
SELECT COUNT(*) FROM ch_notification_users;
```

### Check Unread Notifications
```sql
SELECT * FROM ch_notification_users WHERE read_at IS NULL;
```

---

## 🎯 Success Indicators

✅ All tasks checked = System is ready!

You should see:
- [ ] Bell icon in header with unread count
- [ ] Notifications dropdown when clicking bell
- [ ] Ability to mark notifications as read
- [ ] API endpoints responding correctly
- [ ] Scheduled jobs running automatically
- [ ] Test notifications appearing for different types

---

## 🆘 Need Help?

1. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Test with Artisan Tinker:**
   ```bash
   php artisan tinker
   ```

3. **Review Documentation:**
   - NOTIFICATION_SYSTEM_GUIDE.md
   - INTEGRATION_EXAMPLES.php
   - NOTIFICATION_QUICK_START.php

4. **Verify Requirements:**
   - PHP 8.2+
   - Laravel 10.x or 11.x
   - MySQL/PostgreSQL
   - Redis (optional, for queues)

---

**Last Updated:** January 10, 2026  
**Status:** ✅ Ready for Use
