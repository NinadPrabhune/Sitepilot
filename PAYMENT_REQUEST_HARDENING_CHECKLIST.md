# Payment Request System - Production Hardening Verification Checklist

## Pre-Deployment Verification

### Phase 1: Database Indexes
- [ ] Run migration: `2026_04_10_000000_add_critical_indexes_for_scale.php`
- [ ] Run migration: `2026_04_10_000001_add_active_requests_snapshot_to_payment_requests.php`
- [ ] Verify indexes exist:
```sql
SHOW INDEX FROM payment_requests;
SHOW INDEX FROM payments_module;
SHOW INDEX FROM advance_adjustments;
```

### Phase 2: Index Usage Verification
Run EXPLAIN queries and verify `type` is NOT `ALL`:

```sql
-- Should show 'ref' or 'range', NOT 'ALL'
EXPLAIN SELECT * FROM payment_requests WHERE status = 'pending';
EXPLAIN SELECT * FROM payment_requests WHERE purchase_invoice_id = 1 AND status = 'pending';

-- Should show 'ref', NOT 'ALL'
EXPLAIN SELECT SUM(amount) FROM payments_module WHERE purchase_invoice_id = 1;

-- Should show 'ref', NOT 'ALL'
EXPLAIN SELECT * FROM advance_adjustments WHERE purchase_invoice_id = 1 AND deleted_at IS NULL;
```

### Phase 3: Lock Order Verification
Verify all flows use consistent lock order:

**Expected Lock Order (must be identical in ALL flows):**
1. PurchaseInvoice
2. PurchaseOrder
3. PaymentRequest
4. AdvanceAdjustment

**Check in Controller:**
- [ ] `store()` method uses correct order
- [ ] `approveSingle()` method uses correct order
- [ ] `approvalUpdate()` method uses correct order

**Check in Services:**
- [ ] `PaymentService::createAgainstInvoice()` uses correct order
- [ ] `AdvanceAllocationService::allocateAdvanceToInvoice()` uses correct order
- [ ] `AdvanceAllocationService::allocateAdvanceWithFIFO()` uses correct order

### Phase 4: Snapshot Field Verification
Verify new field added:

```sql
DESCRIBE payment_requests;
-- Should show: active_requests_snapshot
```

- [ ] Migration `2026_04_10_000001_add_active_requests_snapshot_to_payment_requests.php` ran
- [ ] Model `$fillable` includes `active_requests_snapshot`
- [ ] Model `$casts` includes `active_requests_snapshot`

### Phase 5: Query Optimization Verification
**createModal() optimization:**
- [ ] Uses single aggregated query with LEFT JOINs
- [ ] No longer calls getNetPayableAmount() directly
- [ ] All financial values derived from single query result

### Phase 6: Batch Approval Deadlock Fix
- [ ] `approvalUpdate()` uses `orderBy('id')` before `lockForUpdate()`

---

## Post-Deployment Verification (Day 1-7)

### Daily Health Checks
Run these queries daily:

```sql
-- 1. PR Volume
SELECT DATE(created_at), COUNT(*) FROM payment_requests GROUP BY DATE(created_at);

-- 2. Duplicate Pending
SELECT purchase_invoice_id, COUNT(*) FROM payment_requests 
WHERE status = 'pending' AND created_at > NOW() - INTERVAL 24 HOUR
GROUP BY purchase_invoice_id HAVING COUNT(*) > 1;

-- 3. Snapshot Completeness
SELECT COUNT(*) FROM payment_requests 
WHERE status IN ('approved', 'partially_approved') 
AND active_requests_snapshot IS NULL;
```

### Performance Monitoring
- [ ] PR creation time < 2s (average)
- [ ] Approval time < 3s (average)
- [ ] createModal load time < 1s (average)
- [ ] No lock wait > 3s in INNODB_TRX

### Alert Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| PR creation time | > 2s | > 5s |
| Approval time | > 3s | > 8s |
| Lock wait | > 2s | > 5s |
| Daily PR volume | > 50 | > 100 |

---

## Before vs After Performance Impact

### getNetPayableAmount() Call Reduction

**BEFORE:**
- createModal(): 6+ calls
- approveSingle(): 4+ calls
- approvalUpdate(): 4+ calls per PR in batch
- createAgainstInvoice(): 3+ calls

**AFTER:**
- createModal(): 1 aggregated query (all values from single result)
- approveSingle(): Uses snapshots (already captured at approval)
- approvalUpdate(): Uses snapshots (captured once per batch)
- createAgainstInvoice(): Uses snapshot values

**Estimated Improvement:**
- createModal: ~80% reduction in DB queries
- Approval flows: ~60% reduction in DB queries
- Overall: ~70% reduction in heavy aggregation queries

### Lock Contention

**BEFORE:**
- Multiple locks in inconsistent order
- No ordering on batch lock acquisition
- Potential deadlocks under concurrent load

**AFTER:**
- Consistent lock order (Invoice → PO → PR → AA)
- Deterministic ordering with orderBy('id')
- Deadlock risk significantly reduced

---

## Files Modified Summary

### New Files Created:
1. `database/migrations/2026_04_10_000000_add_critical_indexes_for_scale.php`
2. `database/migrations/2026_04_10_000001_add_active_requests_snapshot_to_payment_requests.php`
3. `database/monitoring_queries.sql`

### Files Modified:
1. `app/Models/PaymentRequest.php` - Added active_requests_snapshot
2. `app/Http/Controllers/PaymentRequestController.php` - Lock ordering + snapshots
3. `app/Services/PaymentService.php` - Lock ordering + snapshots
4. `app/Services/AdvanceAllocationService.php` - Lock ordering
5. `app/Providers/AppServiceProvider.php` - Slow query logger
6. `config/app.php` - slow_query_threshold config

---

## Rollback Plan

If issues occur:

1. **Index issues**: 
   ```sql
   ALTER TABLE payment_requests DROP INDEX idx_pr_status;
   ALTER TABLE payment_requests DROP INDEX idx_pr_invoice_status;
   ALTER TABLE payments_module DROP INDEX idx_pm_invoice;
   ALTER TABLE payments_module DROP INDEX idx_pm_po_type;
   ALTER TABLE advance_adjustments DROP INDEX idx_aa_po;
   ```

2. **Snapshot issues**:
   ```sql
   ALTER TABLE payment_requests DROP COLUMN active_requests_snapshot;
   ```

3. **Complete rollback**: 
   ```bash
   php artisan migrate:rollback --step=2
   ```

---

## Final Sign-Off

- [ ] All migrations applied successfully
- [ ] Indexes verified with EXPLAIN
- [ ] Lock order consistent across all flows
- [ ] Snapshots being captured at creation and approval
- [ ] createModal using optimized query
- [ ] Batch approval using orderBy for deterministic locking
- [ ] Slow query logger active
- [ ] Monitoring queries documented and scheduled
- [ ] Alert thresholds defined
- [ ] Team trained on rollback procedures

**Go-Live Approval**: _________________ **Date**: _____________
