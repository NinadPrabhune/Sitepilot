# Notification System Testing Guide

## Implementation Summary

The comprehensive notification system for procurement and payment workflows has been successfully implemented. This guide provides testing steps to verify the system works correctly.

## What Was Implemented

### 1. NotificationService Enhancements
- **getUsersBySiteId()**: Central method to retrieve all users belonging to a site
- **send()**: Centralized notification method with site-based audience targeting
- **Notification Type Constants**: Standardized notification types for all modules
- **15 New Notification Methods**: 3 methods per module (created, updated, status_changed) for:
  - Indent
  - Purchase Order (PO)
  - GRN
  - Purchase Invoice
  - Payment Request

### 2. Fixed Existing Methods
- `createPOGeneratedNotification()`: Now sends to ALL site users (not just Account Managers)
- `createPaymentRequestNotification()`: Now sends to ALL site users (not just Account Managers)
- `createPaymentApprovalNotification()`: Now sends to ALL site users (not just creator)
- `createPaymentApprovalStatusNotification()`: Now sends to ALL site users (not just creator)

### 3. Model Observers Created
- `IndentObserver`: Triggers on create, update, status change
- `PurchaseOrderObserver`: Triggers on create, update, status change
- `GrnObserver`: Triggers on create, update, status change
- `PurchaseInvoiceObserver`: Triggers on create, update, status change
- `PaymentRequestObserver`: Triggers on create, update, status change

### 4. Observer Registration
All observers registered in `AppServiceProvider::boot()`

## Database Schema Verification

✅ **ch_notifications table** supports all required fields:
- `type` - Notification type identifier
- `title` - Notification title
- `message` - HTML message content
- `message_arr` (JSON) - Structured metadata
- `workspace_id` - Workspace association
- `project_id` - Project/site association
- `related_id` - Related record ID
- `related_type` - Related record type
- `action_url` - Redirect URL
- `timestamps` - Created/updated timestamps

✅ **ch_notification_users table** supports:
- `notification_id` - FK to ch_notifications
- `user_id` - FK to users
- `read_at` - Read status timestamp
- `timestamps` - Created/updated timestamps

## Testing Checklist

### Pre-Testing Setup
1. Ensure all migrations have run: `php artisan migrate`
2. Clear cache: `php artisan config:clear && php artisan cache:clear`
3. Ensure you have multiple users in the same site_id for testing

### Test 1: Indent Notifications
**Create Notification:**
1. Create a new Indent via UI or API
2. Verify all users with same `site_id` receive notification
3. Check `ch_notifications` table for record with `type = 'indent_created'`
4. Check `ch_notification_users` table has entries for all site users

**Update Notification:**
1. Update an existing Indent (non-status change)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'indent_updated'`

**Status Change Notification:**
1. Change Indent status (e.g., Open → Partially Closed)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'indent_status_changed'`
4. Verify `message_arr` contains `old_status` and `new_status`

### Test 2: Purchase Order Notifications
**Create Notification:**
1. Create a new Purchase Order
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'po_created'`

**Update Notification:**
1. Update PO (non-status change)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'po_updated'`

**Status Change Notification:**
1. Change PO status (e.g., Draft → Approved)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'po_status_changed'`
4. Test all PO status transitions:
   - Draft → Approved
   - Approved → Partial Received
   - Partial Received → Completed
   - Any status → Rejected
   - Any status → Flagged
   - Any status → Short Closed
   - Any status → Partial
   - Any status → Closed

### Test 3: GRN Notifications
**Create Notification:**
1. Create a new GRN
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'grn_created'`

**Update Notification:**
1. Update GRN (non-status change)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'grn_updated'`

**Status Change Notification:**
1. Change GRN status (e.g., Pending → Approved)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'grn_status_changed'`

### Test 4: Purchase Invoice Notifications
**Create Notification:**
1. Create a new Purchase Invoice
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'invoice_created'`

**Update Notification:**
1. Update Invoice (non-status change)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'invoice_updated'`

**Status Change Notification:**
1. Change Invoice status
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'invoice_status_changed'`

### Test 5: Payment Request Notifications
**Create Notification:**
1. Create a new Payment Request
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'payment_request_created'`

**Update Notification:**
1. Update Payment Request (non-status change)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'payment_request_updated'`

**Status Change Notification:**
1. Change Payment Request status (e.g., pending → approved)
2. Verify all site users receive notification
3. Check `ch_notifications` table for `type = 'payment_request_status_changed'`
4. Test all Payment Request status transitions:
   - pending → approved
   - pending → partially_approved
   - pending → rejected
   - approved → partially_paid
   - approved → paid
   - partially_approved → partially_paid
   - partially_approved → paid
   - partially_paid → paid

### Test 6: Existing Notification Methods
**PO Generated Notification:**
1. Trigger PO generation
2. Verify notification goes to ALL site users (not just Account Managers)
3. Check logs for "All users for site" message

**Payment Request Notification:**
1. Trigger payment request creation via existing workflow
2. Verify notification goes to ALL site users (not just Account Managers)

**Payment Approval Notification:**
1. Approve a payment request
2. Verify notification goes to ALL site users (not just creator)

### Test 7: Self-Notification
1. Create a record as a user
2. Verify the creating user also receives the notification
3. Check `ch_notification_users` table includes the creator's user_id

### Test 8: Notification Metadata
For each notification type, verify `message_arr` contains:
- `module` - Module name (e.g., 'purchase_order')
- `record_id` - ID of the related record
- `action` - Action type ('created', 'updated', 'status_changed')
- `performed_by` - User ID who performed the action
- `old_status` - Previous status (for status changes)
- `new_status` - New status (for status changes)

## SQL Queries for Verification

### Check notifications by type
```sql
SELECT * FROM ch_notifications WHERE type = 'po_created' ORDER BY created_at DESC LIMIT 10;
```

### Check notification recipients
```sql
SELECT n.type, n.title, COUNT(cnu.user_id) as recipient_count
FROM ch_notifications n
JOIN ch_notification_users cnu ON n.id = cnu.notification_id
GROUP BY n.id, n.type, n.title
ORDER BY n.created_at DESC;
```

### Verify all site users receive notifications
```sql
-- Get users in a site
SELECT id, name, email, site_id FROM users WHERE site_id = <SITE_ID>;

-- Check if all users received a specific notification
SELECT u.id, u.name, u.email, cnu.read_at
FROM users u
LEFT JOIN ch_notification_users cnu ON u.id = cnu.user_id AND cnu.notification_id = <NOTIFICATION_ID>
WHERE u.site_id = <SITE_ID>;
```

### Check notification metadata
```sql
SELECT type, message_arr FROM ch_notifications WHERE id = <NOTIFICATION_ID>;
```

## Troubleshooting

### Notifications Not Appearing
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify observers are registered in `AppServiceProvider`
3. Ensure `site_id` is set on the model
4. Check that users have `site_id` set

### Not All Users Receiving Notifications
1. Verify all users in the site have `site_id` set correctly
2. Check `getUsersBySiteId()` is returning correct user IDs
3. Ensure no role-based filtering is interfering

### Status Change Notifications Not Triggering
1. Verify `isDirty('status')` is detecting the change
2. Check that status field is actually being changed
3. Review observer logs for errors

## Performance Considerations

The notification system sends notifications to ALL users in a site. For large sites (100+ users), consider:
1. Implementing queue-based notification sending
2. Adding rate limiting
3. Implementing notification preferences per user

## Future Enhancements

1. **Role-Based Filtering**: Allow users to opt-in/out based on role
2. **Notification Preferences**: Per-user notification settings
3. **Email Notifications**: Add email delivery option
4. **SMS Notifications**: Add SMS delivery for critical notifications
5. **Notification Digest**: Batch notifications into daily/weekly digests

## Rollback Plan

If issues arise, you can disable observers by commenting out the observer registration in `AppServiceProvider::boot()`:

```php
// Comment out to disable observers
// Indent::observe(IndentObserver::class);
// PurchaseOrder::observe(PurchaseOrderObserver::class);
// Grn::observe(GrnObserver::class);
// PurchaseInvoice::observe(PurchaseInvoiceObserver::class);
// PaymentRequest::observe(PaymentRequestObserver::class);
```
