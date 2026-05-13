# Notification System Testing Checklist

## Production Readiness Checklist

### Infrastructure Setup
- [ ] Redis server running and accessible
- [ ] Supervisor installed and configured
- [ ] Queue worker started: `sudo supervisorctl start laravel-queue:*`
- [ ] Laravel Horizon installed (optional but recommended)
- [ ] Pusher credentials verified in production environment
- [ ] Firebase service account credentials configured

### Security Validation
- [ ] Channel authorization tested: users can only access their project channels
- [ ] Project ID injection protection verified
- [ ] API endpoints validated for unauthorized notification sending
- [ ] CSRF protection confirmed for all notification endpoints

### Real Device Testing Matrix

#### Single User Scenarios
- [ ] 1 user, 1 device → 1 push notification received
- [ ] 1 user, 2 devices → 2 push notifications received (all devices)
- [ ] 1 user, app in foreground → notification displayed in-app
- [ ] 1 user, app in background → push notification received
- [ ] 1 user, app killed → push notification received

#### Multi-User Scenarios
- [ ] 2 users in same project → both receive notification
- [ ] User removed from project → no notification received
- [ ] User with no device tokens → notification logged but not pushed
- [ ] User with expired/invalid token → token auto-removed

#### Platform-Specific Testing
**Android:**
- [ ] Foreground notification display
- [ ] Background notification display
- [ ] Killed app notification display
- [ ] Notification tap opens correct screen
- [ ] Multiple devices logged in

**iOS:**
- [ ] Foreground notification display
- [ ] Background notification display
- [ ] Killed app notification display
- [ ] Notification tap opens correct screen
- [ ] APNs certificate valid
- [ ] Background modes enabled in app

### Load Testing
- [ ] Run load test script: `php artisan tinker` → include `tests/NotificationLoadTest.php`
- [ ] Test with 50 burst notifications
- [ ] Monitor queue backlog
- [ ] Check for duplicate notifications
- [ ] Verify Redis memory usage
- [ ] Confirm no timeout errors

### Failure Scenario Testing
- [ ] Redis down → graceful degradation
- [ ] Pusher quota exceeded → logged and alert triggered
- [ ] Firebase quota exceeded → logged and alert triggered
- [ ] Invalid FCM tokens → auto-removed
- [ ] Network timeout → retry mechanism activated

### Metrics and Monitoring
- [ ] Verify notification creation logs appear
- [ ] Confirm broadcast success logs appear
- [ ] Check FCM success/failure metrics
- [ ] Monitor queue processing time
- [ ] Alert on critical errors (Firebase quota, broadcast failures)

### End-to-End Flow Validation
- [ ] Create notification → DB entry created
- [ ] Queue processes notification
- [ ] Pusher event fires → UI updates instantly
- [ ] Firebase push received on mobile device
- [ ] Only authorized users receive notification
- [ ] Read/unread status updates correctly

### Performance Benchmarks
- [ ] Single notification sent in < 2 seconds
- [ ] 50 burst notifications processed in < 30 seconds
- [ ] Queue delay < 5 seconds under normal load
- [ ] No N+1 queries in notification sending
- [ ] Database queries optimized with eager loading

### Post-Deployment Monitoring
- [ ] Monitor Laravel logs for errors
- [ ] Check queue backlog regularly
- [ ] Verify Pusher message usage
- [ ] Track Firebase delivery rates
- [ ] Monitor Redis memory usage
- [ ] Review notification delivery success rate

### Rollback Plan
- [ ] Document rollback procedure
- [ ] Test queue worker stop/start
- [ ] Verify database backup availability
- [ ] Confirm Redis flush procedure
- [ ] Document Pusher disable steps

## Testing Commands

### Start Queue Worker
```bash
php artisan queue:work redis --sleep=3 --tries=3 --timeout=90
```

### Check Failed Jobs
```bash
php artisan queue:failed
php artisan queue:retry all
```

### Run Load Test
```bash
php artisan tinker
include 'tests/NotificationLoadTest.php';
(new Tests\NotificationLoadTest())->run();
```

### Monitor Redis
```bash
redis-cli
> monitor
> info memory
> keys queue:*
```

### Check Logs
```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/queue.log
```

## Success Criteria

All tests pass and:
- Notification delivery rate > 95%
- Average delivery time < 5 seconds
- No duplicate notifications
- No silent failures
- Proper error logging and alerting
