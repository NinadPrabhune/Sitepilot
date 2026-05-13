# Phase A Execution Guide

**Status:** 🚀 READY TO EXECUTE

**Date:** April 28, 2026

---

## 🎯 Critical Focus Points (The 4 Signals That Matter)

### 1. 🔴 DB State Verification (After Scenarios 1 & 5)

**Query to run EVERY TIME:**
```sql
SELECT COUNT(*) 
FROM machinery_ledger
WHERE payment_request_id IS NULL
AND date BETWEEN '2026-01-01' AND '2026-01-31'
AND machinery_id = 1;
```

**Expected:** 0

**If NOT 0:**
- ❌ Silent partial linking
- ❌ Financial integrity bug
- **STOP IMMEDIATELY**

---

### 2. 🔴 Snapshot vs Recalculate Drift (Scenarios 4 & 34)

**Watch for:**
- ✅ Mismatch when ledger modified (expected)
- ✅ NO mismatch when nothing changed (expected)
- ❌ Mismatch when nothing changed (BUG - hash/query inconsistency)

**Check:** `/api/machinery/payment-requests/{id}/debug`

---

### 3. 🔴 Log Order Verification

**Expected sequence in payment_audit:**
```
CREATE → SUBMIT → VERIFY → APPROVE → LOCK
```

**Red flags:**
- APPROVE before VERIFY
- LOCK before validation
- Duplicate APPROVE logs

**Check:** `/api/machinery/payment-logs?payment_request_id={id}`

---

### 4. 🔴 Scenario 31 - Transaction Atomicity (YOUR REAL EXAM)

**After simulated failure, run:**
```sql
SELECT * FROM machinery_payment_periods ORDER BY id DESC LIMIT 1;
SELECT COUNT(*) FROM machinery_ledger WHERE payment_request_id IS NOT NULL;
```

**Must be:**
- No new locked period
- 0 linked entries

**If even 1 row exists:**
- ❌ Transaction not atomic
- **CRITICAL FAILURE - STOP**

---

## ⚡ 5-Round Execution Plan

### Round 1: Baseline Stability
**Scenarios:** 1 → 7 → 1 (re-run)

**Purpose:** Confirm system stability after mutation

**Key Check:** Scenario 1 re-run numbers must match first run exactly

---

### Round 2: Selection Correctness
**Scenarios:** 27 → 28 → 33

**Purpose:** Validate selection logic

**Key Checks:**
- Boundary inclusion (27)
- Reversal exclusion (28)
- Deterministic ordering (33)

---

### Round 3: Drift Detection
**Scenarios:** 4 → 34

**Purpose:** Validate drift detection

**Key Checks:**
- Backdated entry detection (4)
- Immutability violation detection (34)

**Try to break it:**
```sql
-- After Scenario 4 approval blocked, try:
UPDATE machinery_ledger SET amount = amount + 1 WHERE id = {entry_id};
-- Then hit /debug - should show hash_mismatch = true
```

---

### Round 4: Ownership + Locking
**Scenarios:** 5 → 10

**Purpose:** Validate ownership and double-spend prevention

**Key Checks:**
- Period lock created (5)
- Ledger entries linked (5)
- Double-spend query returns 0 (10)

**Double-spend verification query:**
```sql
SELECT id, COUNT(DISTINCT payment_request_id) as request_count
FROM machinery_ledger
WHERE payment_request_id IS NOT NULL
GROUP BY id
HAVING COUNT(DISTINCT payment_request_id) > 1;
```

**Must return:** 0 rows

**Try to break it (After Scenario 5):**
- Insert new ledger entry in same period → Should be blocked
- Update existing entry → Should be detected
- Approve again → Should fail

---

### Round 5: System Safety (CRITICAL)
**Scenarios:** 31 → 32

**Purpose:** Validate system safety under failure and concurrency

**Scenario 31 - Rollback:**
1. Create verified payment request
2. Simulate exception in approve (modify code temporarily to throw after lockPeriod)
3. Execute approval → Should fail
4. **Verify:**
   ```sql
   SELECT * FROM machinery_payment_periods ORDER BY id DESC LIMIT 1;
   -- Must NOT show new lock
   
   SELECT COUNT(*) FROM machinery_ledger WHERE payment_request_id = {request_id};
   -- Must be 0
   ```

**Scenario 32 - Mutation between verify → approve:**
1. Create payment request, submit, verify (status = verified)
2. Insert new ledger entry in period (simulating another user)
3. Attempt approve
4. **Expected:** Approval blocked, status stays verified

**Verify:**
- Status = verified (not approved)
- No period lock created
- No ledger entries linked
- Error message indicates calculation mismatch

---

## 🧪 Post-Approval Resistance Testing

**After Scenario 5 (approval), immediately try:**

1. **Insert new ledger entry in same period:**
   ```sql
   INSERT INTO machinery_ledger (machinery_id, date, amount, entry_direction, entry_type, workspace_id)
   VALUES (1, '2026-01-15', 1000.00, 'credit', 'reading', 1);
   ```
   **Expected:** Next approval for overlapping period should block

2. **Update existing entry:**
   ```sql
   UPDATE machinery_ledger SET amount = amount + 0.01 WHERE id = {linked_entry_id};
   ```
   **Expected:** `/debug` should show hash_mismatch = true

3. **Double-click approve:**
   - Click approve twice rapidly
   **Expected:** Only one succeeds, no duplicate logs, no double linking

---

## 📊 Evidence Capture

**For each scenario, record:**

| Scenario | Status | DB Verified | API Verified | Logs Checked | Resistance Test | Notes |
|----------|--------|-------------|--------------|--------------|-----------------|-------|
| 1 | | | | | | |
| 7 | | | | | | |
| 1-re | | | | | | |
| 27 | | | | | | |
| 28 | | | | | | |
| 33 | | | | | | |
| 4 | | | | | | |
| 34 | | | | | | |
| 5 | | | | | | |
| 10 | | | | | | |
| 31 | | | | | | |
| 32 | | | | | | |

---

## 🚨 Stop Conditions (Hard Rules)

**STOP immediately and restart Phase A from zero if:**

- ❌ Scenario 31: Any row persists after rollback failure
- ❌ Scenario 32: Approval not blocked when ledger changes
- ❌ Scenario 10: Double-spend query returns > 0 rows
- ❌ Scenario 34: Hash mismatch not detected after ledger update
- ❌ DB verification shows unlinked entries after approval
- ❌ Log order shows wrong sequence (approve before verify)

**DO NOT:**
- Continue testing
- "Note for later"
- Skip and come back

**DO:**
- Fix the issue
- Clear test data
- Restart Phase A from Round 1

---

## ✅ "Truly Passed" Criteria

**Phase A is only done when:**

1. ✅ All 12 scenarios executed
2. ✅ All 4 critical signals verified
3. ✅ Resistance tests passed (couldn't break it)
4. ✅ DB verification queries return expected results
5. ✅ Log sequences correct
6. ✅ No Red Flag conditions triggered

**Then and only then:** Proceed to Phase B

---

## 🧪 Advanced Execution Techniques

### 1. Repetition Testing (Don't Trust Single Pass)

**Key scenarios to run twice:**
- Scenario 1 (baseline calculation)
- Scenario 33 (ordering)
- Scenario 4 (backdated detection)

**Method:**
```
Run Scenario 1 → Change entry order slightly → Re-run Scenario 1
```

**If results differ:** ❌ Hidden state dependency bug

---

### 2. Watch for "Too Perfect" Behavior

**Be suspicious if:**
- No logs missing
- No retries triggered
- No warnings

**Real systems under concurrency show:**
- Minor retries
- Lock waits

**Verify logs are actually being written:**
```
GET /api/machinery/payment-logs/recent
```

---

### 3. Negative Space Validation

**After each scenario, check what should NOT exist:**

**After Scenario 5 (approval):**
```sql
SELECT * FROM machinery_payment_periods WHERE is_locked = false;
-- Should NOT include your approved period
```

**After Scenario 32 (mutation blocked):**
```sql
SELECT * FROM machinery_payment_periods WHERE machinery_id = 1 ORDER BY id DESC;
-- Should NOT have a new row
```

**Bugs hide in:** unexpected extra rows, not missing ones

---

### 4. Time-Based Edge Testing

**Scenario 32 variation with delay:**
1. Verify payment request
2. **Wait 30-60 seconds**
3. Insert ledger entry
4. Attempt approve

**This simulates:** real-world delay between users

**Some systems only fail under delay**

---

### 5. Precision Trap Testing

**Force precision edge case:**
```sql
UPDATE machinery_ledger SET amount = 1000.005 WHERE id = {entry_id};
```

**Then:**
- Recalculate
- Check diff

**If rounding inconsistent:** → Future reconciliation issues

---

## 🔥 What Success Actually Looks Like

**When Phase A is truly solid, you will observe:**

**Behavior Patterns:**
- ✅ Approval never succeeds after mutation
- ✅ Hash always detects change (no exception)
- ✅ DB always matches snapshot intent
- ✅ No partial states EVER exist

**Psychological Signal:**
> "I tried to break it… but it refuses to break."

**That's when you're done.**

---

## 🚀 Start Round 1

**Ready to execute. Begin with Scenario 1.**

**Access URLs:**
- Create: `/machinery/payment-requests/create`
- List: `/machinery/payment-requests`
- Debug: `/api/machinery/payment-requests/{id}/debug`
- Recalculate: `/api/machinery/payment-requests/{id}/recalculate`
- Logs: `/api/machinery/payment-logs?payment_request_id={id}`

**Good luck. Stay strict.**
