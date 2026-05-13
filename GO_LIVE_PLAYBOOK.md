# Go-Live Playbook

Follow this exact sequence for production deployment.

## Phase 0: Deploy (Feature Flag OFF)

**Configuration:**
```bash
PAYMENTS_ENFORCE_REQUEST=false
```

**Immediately Run (5-min smoke test):**

1. Create PO with grand_total = ₹50,000
2. Create Payment Request (TYPE_PO_ADVANCE) for ₹50,000
3. Approve request
4. Execute payment

**Verify:**
- [ ] Payment row created in payments_module
- [ ] payment_request_id linked correctly
- [ ] Ledger entry created
- [ ] paid_amount updated on payment_request
- [ ] Status updated to PAID

**What this confirms:**
- DB constraints working
- Transactions executing
- Service wiring correct
- Ledger integration working

## Phase 1: Silent Monitoring (1-2 Hours)

**Watch logs in real time:**
- `payment_audit` channel
- `laravel.log`

**You WANT to see:**
- Normal payment logs
- Occasional idempotency hits (OK - normal retry behavior)

**You DO NOT want:**
- ❌ Repeated deadlock failures (after 3 retries)
- ❌ Ledger failures
- ❌ Exceptions in createPaymentFromRequest
- ❌ Payment request status mismatches

## Phase 2: Enable Enforcement

**Configuration:**
```bash
PAYMENTS_ENFORCE_REQUEST=true
```

**Immediately test:**

1. Try direct payment → Should be blocked with 403 error
2. Try payment via request → Should work normally

**Monitor for:**
- ⚠️ "Blocked direct payment attempt" logs (expected initially)
- ⚠️ User confusion / support tickets (may need training)

## Phase 3: Stabilize (24-48 Hours)

**Track metrics (see below)**
**Validate no financial drift**

**After stability:**
- 🧹 Remove legacy routes:
  - `createFromPo()`
  - `createFromInvoice()`
  - Legacy store() logic
- 🔒 Hard-enforce request-only flow
- Remove feature flag (hardcode enforcement)

## Live Health Queries (Run Periodically)

### 1. Idempotency Integrity Check

```sql
SELECT idempotency_key, COUNT(*) as count
FROM payments_module
WHERE idempotency_key IS NOT NULL
GROUP BY idempotency_key
HAVING COUNT(*) > 1;
```

**Expected:** 0 rows (no duplicates)

**If non-zero:** Investigate UI double-submit or API retry issues

### 2. Overpayment Check

```sql
SELECT id, approved_amount, paid_amount,
       (paid_amount - approved_amount) as overpayment
FROM payment_requests
WHERE paid_amount > approved_amount;
```

**Expected:** 0 rows (no overpayments)

**If non-zero:** Critical - investigate payment logic immediately

### 3. Orphan Payments (Safety Check)

```sql
SELECT id, payment_number, amount, payment_date
FROM payments_module
WHERE payment_request_id IS NULL
AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

**Expected:** 0 rows (all payments linked to requests after enforcement)

**If non-zero:** May indicate bypass attempts or legacy payments

### 4. Foreign Key Verification

```sql
SELECT CONSTRAINT_NAME, DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE TABLE_NAME = 'payments_module'
AND REFERENCED_TABLE_NAME = 'payment_requests';
```

**Expected:** `payments_module_payment_request_id_foreign`, `RESTRICT`

### 5. Index Verification

```sql
SHOW INDEX FROM payments_module
WHERE Key_name IN (
    'unique_payment_idempotency',
    'idx_payment_request_id',
    'idx_po_id',
    'idx_invoice_id'
);
```

**Expected:** All 4 indexes present

## Metrics to Watch (First 24 Hours)

| Metric | Healthy Signal | Risk Signal | Alert Threshold |
|--------|----------------|-------------|-----------------|
| Payment success rate | ~100% | <95% | <95% |
| Deadlock retries | Rare | Frequent spikes | >5/min sustained |
| Idempotency hits | Low | Very high | >20% of payments |
| Overpayment errors | Rare | Frequent | >5% of attempts |
| Blocked direct payments | Initial spike | Should drop | Increasing trend |

## Real-World Signals (Don't Ignore)

### 1. High Idempotency Hits

**Likely causes:**
- UI double-submit issue
- Slow API response
- Network retry logic

**Action:**
- Disable button after click (UI fix)
- Add loading state to payment button
- Review API response times

### 2. Frequent Deadlocks

**Likely causes:**
- High concurrency on same PO/request
- Lock order inconsistency
- Long-running transactions

**Action:**
- Increase retry delay (100ms → 200ms)
- Review lock order consistency across codebase
- Check for long-running queries in transaction
- Consider reducing transaction scope

### 3. Many Blocked Direct Payments

**Not a bug — UX gap**

**Action:**
- Add UI hint: "Create Payment Request first"
- Update user documentation
- Provide training session
- Consider redirect message improvement

### 4. Cross-Supplier Attempts

**Likely causes:**
- User error (wrong supplier selected)
- Potential fraud attempt
- Data inconsistency

**Action:**
- Investigate pattern
- If user error: improve UI validation
- If fraud: security review

## Optional: Daily Integrity Job (Cron)

**Purpose:** Early warning system for data drift and silent bugs

**Implementation:**

```php
// app/Console/Commands/CheckPaymentIntegrity.php
class CheckPaymentIntegrity extends Command
{
    protected $signature = 'payments:check-integrity';
    protected $description = 'Verify payment request paid_amount matches actual payments';

    public function handle()
    {
        $mismatches = 0;

        PaymentRequest::whereNull('deleted_at')
            ->chunk(100, function ($requests) use (&$mismatches) {
                foreach ($requests as $request) {
                    $actualPaid = $request->payments()->sum('amount');
                    $recordedPaid = (float) $request->paid_amount;

                    if (abs($actualPaid - $recordedPaid) > 0.01) {
                        $mismatches++;
                        Log::error('Payment integrity mismatch detected', [
                            'payment_request_id' => $request->id,
                            'recorded_paid' => $recordedPaid,
                            'actual_paid' => $actualPaid,
                            'difference' => $actualPaid - $recordedPaid,
                            'request_type' => $request->type,
                        ]);
                    }
                }
            });

        if ($mismatches > 0) {
            Log::error("Payment integrity check complete: {$mismatches} mismatches found");
        } else {
            Log::info('Payment integrity check complete: no mismatches');
        }

        return $mismatches === 0 ? 0 : 1;
    }
}
```

**Schedule:** Run daily via cron
```bash
0 2 * * * php artisan payments:check-integrity
```

**What this detects:**
- Data drift
- Silent bugs
- Manual DB modifications
- Race condition edge cases

## Rollback Decision Tree

### When to Roll Back

**Roll back immediately if:**
- Payment success rate < 90%
- Overpayment errors > 10%
- Deadlock retries failing consistently
- Ledger integrity issues
- Data corruption detected

**Roll back steps:**
1. Set `PAYMENTS_ENFORCE_REQUEST=false`
2. Verify legacy routes work
3. Investigate root cause
4. Apply fix
5. Redeploy with flag=false
6. Gradually re-enable

### When to Continue

**Continue if:**
- Payment success rate > 95%
- Occasional deadlocks (resolving after retry)
- Idempotency hits < 20%
- No data integrity issues
- User adoption improving

## Communication Plan

### Pre-Deployment (1 week before)
- Notify team of upcoming changes
- Schedule training sessions
- Prepare user documentation
- Set up monitoring dashboards

### Deployment Day
- Deploy during low-traffic period
- Have team on standby
- Monitor logs continuously for 2 hours
- Be ready to rollback if needed

### Post-Deployment (1 week)
- Daily metric reviews
- User feedback collection
- Address UX issues
- Plan legacy route removal

## Success Criteria

**Deployment successful when:**
- [ ] All smoke tests pass
- [ ] Payment success rate > 98%
- [ ] No data integrity issues
- [ ] Deadlock retries < 1% of payments
- [ ] User adoption rate > 80%
- [ ] Support tickets < 5/week related to new flow

## Emergency Contacts

**For technical issues:**
- Check logs: `payment_audit`, `laravel.log`
- Run health queries
- Review this playbook

**For user issues:**
- Refer to user documentation
- Provide training support
- Collect feedback for improvements

## Final Verification Checklist

Before declaring deployment successful:

- [ ] Phase 0 smoke test passed
- [ ] Phase 1 monitoring clean (1-2 hours)
- [ ] Phase 2 enforcement enabled
- [ ] Phase 3 stabilization period complete (24-48 hours)
- [ ] All health queries returning 0 rows
- [ ] Metrics within healthy ranges
- [ ] No critical issues detected
- [ ] Team trained on new workflow
- [ ] User documentation updated

---

## System Reality

You've built:
- ✔ Approval-enforced payment system
- ✔ Concurrency-safe transaction layer
- ✔ Idempotent execution
- ✔ Fraud protection (cross-supplier validation)
- ✔ Full audit trail (before/after snapshots)
- ✔ Production-safe migrations
- ✔ Backward-compatible rollout

**This is financial infrastructure, not just a module.**

Deploy with confidence. Monitor closely. Stabilize before locking down.
