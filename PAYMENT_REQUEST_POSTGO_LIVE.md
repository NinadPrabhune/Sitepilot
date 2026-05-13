# Payment Request - Post-Go-Live Monitoring (Day 0-7)

## 📅 DAY 0 - GO-LIVE (April 9, 2026)

### Pre-Launch Status
- [x] Migrations applied
- [x] Config/cache cleared (partial - route cache has pre-existing issue)
- [x] Go-live document created

### ⚠️ Known Issue
Route cache has pre-existing duplicate `users.index` route issue (not related to Payment Request)

### Soft Launch Configuration
**Limited to:**
- Admin
- Account Manager

**Initial Usage:** 1-2 invoices only

---

## 📊 DAILY HEALTH CHECKLIST

### Day 0 (Today)
| Check | Status |
|-------|--------|
| System optimized | ✅ Done |
| Routes working | ✅ 15 PR routes active |
| Migrations complete | ✅ 11 PR migrations ran |

### Day 1 (Tomorrow)
- [ ] Run DB sanity check
- [ ] Check for duplicate pending requests
- [ ] Verify Payment → PR linkage

---

## 🔍 DAILY VERIFICATION QUERIES

### Query 1: Duplicate Pending Requests
```sql
SELECT purchase_invoice_id, COUNT(*) as cnt 
FROM payment_requests 
WHERE status = 'pending' 
GROUP BY purchase_invoice_id 
HAVING cnt > 1;
```
**Expected:** 0 rows

### Query 2: Payment vs Request Amount Match
```sql
SELECT pr.id, pr.requested_amount, pr.approved_amount, 
       pm.amount as paid_amount
FROM payment_requests pr
LEFT JOIN payments_modules pm ON pm.payment_request_id = pr.id
WHERE pr.status IN ('approved', 'partially_approved');
```
**Expected:** approved_amount = paid_amount

### Query 3: Financial Snapshots Captured
```sql
SELECT COUNT(*) as total,
       SUM(CASE WHEN net_payable_snapshot IS NOT NULL THEN 1 ELSE 0 END) as with_snapshots
FROM payment_requests 
WHERE status IN ('approved', 'partially_approved');
```
**Expected:** all approved should have snapshots

### Query 4: Invoice Payment Status Sync
```sql
SELECT pi.id, pi.invoice_number, pi.payment_status,
       (SELECT SUM(amount) FROM payments_modules WHERE purchase_invoice_id = pi.id) as total_paid
FROM purchase_invoices pi
WHERE pi.payment_status != 'paid'
AND (SELECT SUM(amount) FROM payments_modules WHERE purchase_invoice_id = pi.id) >= pi.grand_total;
```
**Expected:** 0 rows (mismatched status)

---

## 🚨 RED FLAG CONDITIONS

If ANY detected → IMMEDIATE ACTION:

| Flag | Action |
|------|--------|
| Payment > Invoice amount | Disable routes, investigate |
| Duplicate payment entries | DB query, remove duplicates |
| Negative balance | Check advance allocation |
| Multiple pending for same invoice | Block user action |

### Emergency Disable Routes:
```php
// In routes/web.php - comment out:
// Route::resource('payment-request', PaymentRequestController::class);
```

---

## 📈 DAILY METRICS TO TRACK

| Day | Total PRs | Approved | Pending | Rejected | Total Paid |
|-----|-----------|----------|---------|----------|------------|
| 0   |           |          |         |          |            |
| 1   |           |          |         |          |            |
| 2   |           |          |         |          |            |
| 3   |           |          |         |          |            |
| 4   |           |          |         |          |            |
| 5   |           |          |         |          |            |
| 6   |           |          |         |          |            |
| 7   |           |          |         |          |            |

---

## 📋 USER FEEDBACK LOG

| Date | User | Feedback | Action Taken |
|------|------|----------|--------------|
|      |      |          |              |

---

## ✅ FINAL STABILITY CONFIRMATION (Day 7)

- [ ] No financial discrepancies
- [ ] No user confusion reported
- [ ] No rollback needed
- [ ] Ledger matches payments
- [ ] Advance allocation correct
- [ ] Snapshot data accurate

**System Status:** _______________