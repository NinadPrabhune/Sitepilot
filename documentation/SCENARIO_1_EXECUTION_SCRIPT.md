# Scenario 1: Full Month Payment Creation - Execution Script

**Status:** Ready to Execute
**Scenario:** 1 (Round 1 of Phase A)
**Objective:** Full Month Payment Creation with mixed credit/debit entries

---

## 🚨 MICRO-CHECKS (Run BEFORE Step 1 - Critical)

These detect hidden state that would corrupt your test results.

### Micro-Check 1: Hidden Data Pollution

```sql
SELECT COUNT(*) as existing_entries
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31';
```

**Expected:** 0

**🚨 STOP IF > 0:** You're not in clean state. Clean data before proceeding.

**Record:** existing_entries: ___

### Micro-Check 2: Reversal Leakage Check

```sql
SELECT COUNT(*) as reversal_count
FROM machinery_ledger
WHERE machinery_id = 1
AND is_reversal = true
AND date BETWEEN '2026-01-01' AND '2026-01-31';
```

**Expected:** 0

**🚨 STOP IF > 0:** Filter consistency compromised.

**Record:** reversal_count: ___

### Micro-Check 3: Duplicate Entry Edge Detection

```sql
SELECT date, amount, entry_direction, COUNT(*) as cnt
FROM machinery_ledger
WHERE machinery_id = 1
GROUP BY date, amount, entry_direction
HAVING COUNT(*) > 1;
```

**Expected:** 0 rows

**🚨 STOP IF rows returned:** Duplicate entries exist - calculation may "look correct" but be wrong.

**Record:** duplicates_found: ___ (yes/no)

### Micro-Check 4: Workspace Contamination (Silent Cross-Workspace Bug)

```sql
SELECT COUNT(*) as wrong_workspace_entries
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31'
AND workspace_id != 1;
```

**Expected:** 0

**🚨 STOP IF > 0:** Your selection query might accidentally include/exclude entries. Idempotency/isolation tests will fail later.

**Record:** wrong_workspace_entries: ___

### Micro-Check 5: Timezone Boundary Drift (Edge Case)

```sql
SELECT id, date FROM machinery_ledger
WHERE DATE(date) != date;
```

**Expected:** 0 rows

**🚨 STOP IF rows exist:** Datetime values being truncated inconsistently. Scenario 27 (boundary) will fail later.

**Record:** timezone_drift_found: ___ (yes/no)

---

## Step 1: CAPTURE BASELINE (Before Any Action)

```sql
-- Run these 3 queries and record the results
SELECT COUNT(*) as payment_requests_count FROM machinery_payment_requests;
SELECT COUNT(*) as payment_periods_count FROM machinery_payment_periods;
SELECT COUNT(*) as linked_ledger_count FROM machinery_ledger WHERE payment_request_id IS NOT NULL;
```

**Record Results:**
- payment_requests_count: ___
- payment_periods_count: ___
- linked_ledger_count: ___

---

## Step 2: CREATE LEDGER ENTRIES

```sql
-- Insert 5 test ledger entries for machinery_id = 1
INSERT INTO machinery_ledger (machinery_id, date, amount, entry_direction, entry_type, workspace_id, created_by, created_at) VALUES
(1, '2026-01-05', 5000.00, 'credit', 'reading', 1, 1, NOW()),
(1, '2026-01-10', 2500.00, 'credit', 'diesel', 1, 1, NOW()),
(1, '2026-01-15', 1500.00, 'credit', 'maintenance', 1, 1, NOW()),
(1, '2026-01-08', 1000.00, 'debit', 'advance', 1, 1, NOW()),
(1, '2026-01-20', 500.00, 'debit', 'transfer', 1, 1, NOW());
```

**Verify entries created:**
```sql
SELECT id, date, amount, entry_direction, entry_type
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31'
ORDER BY date, id;
```

**Expected:** 5 rows with IDs you need to note

---

## Step 3: VERIFY MANUAL CALCULATION

```sql
SELECT 
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE 0 END) as credits,
    SUM(CASE WHEN entry_direction = 'debit' THEN amount ELSE 0 END) as debits,
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE -amount END) as net_payable
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31';
```

**Expected:**
- credits: 9000.00
- debits: 1500.00
- net_payable: 7500.00

**If different:** STOP and investigate

---

## Step 4: CREATE PAYMENT REQUEST (Via UI)

**Navigate to:** `/machinery/payment-requests/create`

**Form Values:**
- Machinery: Select machinery_id = 1
- Supplier: Select any supplier
- Period Start: 2026-01-01
- Period End: 2026-01-31
- Idempotency Key: `phaseA-s1`

**Action:** Click "Calculate from Ledger"

**Wait for:** Calculation results to appear

**Record displayed values:**
- Credits: ___
- Debits: ___
- Net Payable: ___
- Entry Count: ___

---

## Step 4b: SOURCE OF ENTRIES VERIFICATION (CRITICAL)

**Don't just trust totals. Confirm which rows were actually used.**

**Query DB for expected entries:**
```sql
SELECT id, date, amount, entry_direction
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31'
ORDER BY date, id;
```

**Record expected entry IDs:**
- ID 1: ___
- ID 2: ___
- ID 3: ___
- ID 4: ___
- ID 5: ___

**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/debug`

**Verify:**
- `ledger_entries.ids` matches your 5 expected IDs exactly
- `ledger_entries.count` = 5
- First 5 entries from debug response match DB query above

**🚨 STOP IF mismatch:** Phantom inclusion/exclusion bug or query inconsistency across layers.

**Record:**
- Debug entry IDs match DB: ___ (yes/no)
- Order preserved (date, id): ___ (yes/no)

---

## Step 5: SNAPSHOT VERIFICATION (After UI Calculation)

**Critical Check:** Verify snapshot was actually created, not lazily deferred

```sql
SELECT COUNT(*) as snapshot_count
FROM machinery_payment_requests
WHERE entries_hash IS NOT NULL
AND status = 'draft'
ORDER BY id DESC LIMIT 1;
```

**Expected:** 1 (the newly created request has entries_hash)

**🚨 STOP IF 0:** Snapshot not created - audit trail broken

**Also verify:**
```sql
SELECT entries_count_check
FROM (
    SELECT JSON_LENGTH(audit_snapshot, '$.ledger_entry_ids') as entries_count_check
    FROM machinery_payment_requests
    ORDER BY id DESC LIMIT 1
) as subquery;
```

**Expected:** 5 (entry count matches)

**Record:**
- snapshot_created: ___ (yes/no)
- entries_count_in_snapshot: ___

---

## Step 6: POST-CREATION DELTA CHECK

```sql
-- Run these 3 queries again
SELECT COUNT(*) as payment_requests_count FROM machinery_payment_requests;
SELECT COUNT(*) as payment_periods_count FROM machinery_payment_periods;
SELECT COUNT(*) as linked_ledger_count FROM machinery_ledger WHERE payment_request_id IS NOT NULL;
```

**Expected Changes:**
- payment_requests_count: +1 (should be baseline + 1)
- payment_periods_count: NO CHANGE (must stay at baseline value)
- linked_ledger_count: NO CHANGE (must stay at 0)

**🚨 STOP IF:**
- payment_periods_count increased → premature locking bug
- linked_ledger_count increased → premature linking bug

---

## Step 6: GET PAYMENT REQUEST ID

```sql
SELECT id, status, credits, debits, net_payable, entries_hash, audit_snapshot
FROM machinery_payment_requests
ORDER BY id DESC LIMIT 1;
```

**Record:**
- payment_request_id: ___
- status: ___ (should be 'draft')
- credits: ___ (should be 9000.00)
- debits: ___ (should be 1500.00)
- net_payable: ___ (should be 7500.00)
- entries_hash: ___ (copy this value)

---

## Step 7: API VALIDATION - DEBUG ENDPOINT

**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/debug`

**Record from response:**
- `calculation_snapshot.credits`: ___
- `calculation_snapshot.debits`: ___
- `calculation_snapshot.net_payable`: ___
- `calculation_snapshot.entries_hash`: ___
- `current_calculation.credits`: ___
- `current_calculation.debits`: ___
- `current_calculation.net_payable`: ___
- `current_calculation.entries_hash`: ___
- `calculation_mismatch.credits_mismatch`: ___ (should be false)
- `calculation_mismatch.debits_mismatch`: ___ (should be false)
- `calculation_mismatch.net_payable_mismatch`: ___ (should be false)
- `calculation_mismatch.hash_mismatch`: ___ (should be false)
- `ledger_entries.count`: ___ (should be 5)

**Verify:**
- `calculation_snapshot.entries_hash` matches DB entries_hash
- All mismatch flags are false
- Ledger entry count = 5

---

## Step 8: HASH STABILITY CHECK (Strengthened)

**Phase A: Rapid succession calls**
```
GET /api/machinery/payment-requests/{payment_request_id}/debug
(wait 2-3 seconds)
GET /api/machinery/payment-requests/{payment_request_id}/debug
```

**Record `current_calculation.entries_hash`:**
- Call 1: ___
- Call 2 (after delay): ___

**Phase B: Hash contamination test**

**Modify updated_at (which should NOT affect hash):**
```sql
UPDATE machinery_ledger 
SET updated_at = NOW() 
WHERE id = (SELECT id FROM machinery_ledger WHERE machinery_id = 1 LIMIT 1);
```

**Call again:**
```
GET /api/machinery/payment-requests/{payment_request_id}/debug
```

**Record `current_calculation.entries_hash`:**
- Call 3 (after updated_at change): ___

**Expected:** All 3 hashes IDENTICAL (updated_at is not in hash)

**🚨 STOP IF:** Hash changed after updated_at modification → hash includes wrong fields

**Restore updated_at:**
```sql
-- No need to restore - updated_at change doesn't affect business logic
```

---

## Step 9: API VALIDATION - RECALCULATE ENDPOINT

**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/recalculate`

**Record from response:**
- `original.credits`: ___ (should be 9000.00)
- `original.debits`: ___ (should be 1500.00)
- `original.net_payable`: ___ (should be 7500.00)
- `current.credits`: ___ (should be 9000.00)
- `current.debits`: ___ (should be 1500.00)
- `current.net_payable`: ___ (should be 7500.00)
- `diff.credits`: ___ (should be 0)
- `diff.debits`: ___ (should be 0)
- `diff.net_payable`: ___ (should be 0)
- `has_mismatch`: ___ (should be false)
- `can_approve`: ___ (should be true)

**🚨 STOP IF:**
- has_mismatch = true → query parity broken
- Any diff ≠ 0 → unexpected drift

---

## Step 10: LOG VERIFICATION

**Call:** `GET /api/machinery/payment-logs?payment_request_id={payment_request_id}`

**Verify logs contain:**
- CREATE event
- CALCULATION event
- NO verify event (not yet verified)
- NO approve event (not yet approved)

**Watch for:**
- ❌ Duplicate CREATE logs
- ❌ Missing logs
- ❌ Wrong sequence

---

## Step 11: NEGATIVE SPACE CHECK

```sql
-- Confirm what does NOT exist
SELECT * FROM machinery_payment_periods;
-- Expected: 0 rows (or only pre-existing rows, not the new one)

SELECT * FROM machinery_ledger WHERE payment_request_id IS NOT NULL;
-- Expected: 0 rows
```

**🚨 STOP IF:** Any rows found → premature linking/locking

---

## Step 12: PRECISION VALIDATION

```sql
-- Verify precision matches
SELECT 
    SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE 0 END) -
    SUM(CASE WHEN entry_direction = 'debit' THEN amount ELSE 0 END) as manual_net
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31';
```

**Compare with:** `net_payable` from payment request

**Tolerance:** ±0.01

**🚨 STOP IF:** Difference > 0.01 → decimal handling issue

---

## Step 12b: ENTRY SET EQUALITY (Not Just Sum Equality)

**Critical Check:** Two different sets of entries can produce same totals. Verify exact entry count.

```sql
SELECT COUNT(DISTINCT id) as actual_entry_count
FROM machinery_ledger
WHERE machinery_id = 1
AND date BETWEEN '2026-01-01' AND '2026-01-31';
```

**Compare with:**
- `entries_count` from snapshot (should be 5)
- `ledger_entries.count` from debug endpoint (should be 5)

**🚨 STOP IF mismatch:**
- ❌ Silent inclusion/exclusion bug
- ❌ Financial correctness illusion

**Record:**
- SQL count: ___
- Snapshot count: ___
- Match: ___ (yes/no)

---

## Step 13: MINI RESISTANCE TEST (Upgraded - Drift Detection)

**Make it stricter: modify by 1.00 instead of 0.01 for clearer detection**

**A. Modify one ledger entry:**
```sql
-- Get the first ledger entry ID
SELECT id FROM machinery_ledger WHERE machinery_id = 1 ORDER BY id LIMIT 1;

-- Update it by 1.00 (clearer detection than 0.01)
UPDATE machinery_ledger 
SET amount = amount + 1.00 
WHERE id = {first_entry_id};
```

**B. Check debug endpoint (hash detection):**
**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/debug`

**Expected:**
- `calculation_mismatch.hash_mismatch`: **true** ✅
- `current_calculation.entries_hash` ≠ `calculation_snapshot.entries_hash`

**🚨 STOP IF:** hash_mismatch = false → hash doesn't detect financial changes

**C. Check recalculate endpoint (value detection):**
**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/recalculate`

**Expected:**
- `has_mismatch`: **true** ✅
- `diff.net_payable`: **1.00** ✅
- `can_approve`: **false** ✅

**🚨 STOP IF:**
- has_mismatch = false → drift detection broken
- diff ≠ 1.00 → calculation error
- can_approve = true → approval safety broken

**D. Check UI behavior:**
**Navigate to:** `/machinery/payment-requests/{payment_request_id}`

**Expected:**
- "Recalculate" button shows mismatch warning
- "Approve" button should be **disabled** or show error on click
- UI reflects calculation difference

**🚨 STOP IF:** UI allows approval with mismatch → safety layer broken

**E. Hash Integrity Test (entry_type modification):**

**Modify entry_type (which SHOULD affect hash):**
```sql
UPDATE machinery_ledger 
SET entry_type = 'modified_test' 
WHERE id = {first_entry_id};
```

**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/debug`

**Expected:**
- `calculation_mismatch.hash_mismatch`: **true** ✅

**🚨 STOP IF:** hash_mismatch = false → Hash is missing critical field (entry_type should be included)

**Restore entry_type:**
```sql
UPDATE machinery_ledger 
SET entry_type = 'reading'  -- or original value
WHERE id = {first_entry_id};
```

**F. Restore Amount + Revert & Recalculate Stability Test:**

**Restore the amount change (CRITICAL - don't leave dirty state):**
```sql
UPDATE machinery_ledger 
SET amount = amount - 1.00 
WHERE id = {first_entry_id};
```

**Verify clean state restored:**
**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/recalculate`

**Expected:**
- `has_mismatch`: **false** (back to normal)
- `can_approve`: **true** (approval now allowed)

**Call:** `GET /api/machinery/payment-requests/{payment_request_id}/debug`

**Verify revert stability:**
- `current_calculation.entries_hash` = `calculation_snapshot.entries_hash` (original hash restored)
- `calculation_mismatch.hash_mismatch`: **false**

**🚨 STOP IF:**
- Hash not restored to original → Non-deterministic hashing
- diff ≠ 0 → Floating drift or ordering issue

---

## ✅ SCENARIO 1 PASS CRITERIA (Strict - All Must Pass)

### Data Integrity
- ✅ Credits = 9000.00, Debits = 1500.00, Net = 7500.00
- ✅ 5 entries only, no reversals included
- ✅ No duplicates in ledger
- ✅ Manual SQL SUM matches stored net_payable (≤ 0.01 diff)
- ✅ No workspace contamination
- ✅ No timezone boundary drift

### Source Verification
- ✅ Debug entry IDs match DB exactly
- ✅ Order preserved (date, id)
- ✅ No phantom inclusion/exclusion

### Snapshot Integrity
- ✅ entries_hash IS NOT NULL (snapshot created)
- ✅ entries_count in snapshot = 5
- ✅ Entry IDs in snapshot match actual ledger IDs
- ✅ Sorted order maintained (date, id)

### System Behavior
- ✅ Payment requests count +1
- ✅ Payment periods count UNCHANGED (no premature locking)
- ✅ Linked ledger count = 0 (no premature linking)
- ✅ Status = 'draft'

### Entry Set Equality
- ✅ SQL count = 5
- ✅ Snapshot count = 5
- ✅ No silent inclusion/exclusion

### Determinism & Stability
- ✅ Hash stable across rapid calls (Call 1 = Call 2)
- ✅ Hash unchanged after updated_at modification (Call 1 = Call 3)
- ✅ All debug mismatch flags = false initially
- ✅ Recalculate: has_mismatch = false, can_approve = true

### Drift Detection - Amount Modification
- ✅ After +1.00 modification: hash_mismatch = true
- ✅ After +1.00 modification: diff.net_payable = 1.00
- ✅ After +1.00 modification: can_approve = false
- ✅ UI blocks approval when mismatch detected

### Hash Integrity - Entry Type Detection
- ✅ Hash detects entry_type modification
- ✅ All critical fields included in hash

### Revert & Recalculate Stability
- ✅ Hash restored to original after revert
- ✅ has_mismatch = false after restore
- ✅ can_approve = true after restore
- ✅ diff = 0 after restore

### Audit Trail
- ✅ Logs present (CREATE event)
- ✅ Correct sequence (no verify/approve yet)
- ✅ No duplicate logs
- ✅ Final linked entries count = 0

---

## 📊 RECORD YOUR RESULTS

### Micro-Checks (Before Start - 5 Checks)
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Existing entries | 0 | | |
| Reversal entries | 0 | | |
| Duplicates | none | | |
| Workspace contamination | 0 | | |
| Timezone drift | none | | |

### Calculations
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Credits | 9000.00 | | |
| Debits | 1500.00 | | |
| Net Payable | 7500.00 | | |
| Entry Count | 5 | | |
| Manual SQL SUM | 7500.00 | | |
| Precision diff | ≤0.01 | | |

### Source Verification (Step 4b)
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Debug entry IDs match DB | yes | | |
| Order preserved (date, id) | yes | | |
| Count matches | 5 | | |

### DB State Changes
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| PR Count Delta | +1 | | |
| PP Count Delta | 0 | | |
| Linked Ledger Delta | 0 | | |
| Status | draft | | |

### Snapshot Verification
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| entries_hash exists | yes | | |
| entries_count = 5 | yes | | |
| IDs match | yes | | |

### Entry Set Equality (Step 12b)
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| SQL count | 5 | | |
| Snapshot count | 5 | | |
| Counts match | yes | | |

### Hash Stability
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Call 1 hash | ___ | | |
| Call 2 hash (delay) | = Call 1 | | |
| Call 3 hash (updated_at) | = Call 1 | | |

### API Validation (Clean State)
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Debug mismatch flags | all false | | |
| Recalculate has_mismatch | false | | |
| Recalculate can_approve | true | | |

### Resistance Test - Amount Modification (After +1.00)
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Debug hash_mismatch | true | | |
| Recalculate has_mismatch | true | | |
| Recalculate diff | 1.00 | | |
| Recalculate can_approve | false | | |
| UI blocks approval | yes | | |

### Resistance Test - Entry Type Modification
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Hash detects entry_type change | true | | |

### Resistance Test - Revert & Stability
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Hash restored to original | yes | | |
| has_mismatch | false | | |
| can_approve | true | | |
| diff = 0 | yes | | |

### Audit
| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Logs present | yes | | |
| Sequence correct | yes | | |
| No duplicates | yes | | |
| Linked entries count (final) | 0 | | |

**Overall Status:** ___

**If ALL pass → Proceed to Scenario 7**
**If ANY fail → STOP, document, fix, restart Phase A**

---

## 🚀 IF ALL PASS → Proceed to Scenario 7 (Negative Payable)

**If ANY fail:** STOP, document, fix, restart Phase A from Scenario 1
