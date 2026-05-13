# Machinery Payment System - Real-World Test Scenarios

## Phase 2.5 Validation - Critical Test Cases

**Purpose:** Validate real-world workflows before Phase 3 (Full Production UI)

**Execution Status:** Pending

---

## ⚠️ CRITICAL: Structured Test Execution

**DO NOT run tests in numerical order (1-30).** Tests must be executed in phases to avoid:
- False positives from dirty state
- Destructive operations corrupting later tests
- Dependency issues between scenarios

---

## 🚨 Red Flag Stop Conditions

**STOP IMMEDIATELY** if any of these occur during testing:

- ❌ Double payment possible (entries linked to multiple requests)
- ❌ entries_hash mismatch without detection
- ❌ Approved request recalculates differently
- ❌ Ledger entry linked to 2 payment requests
- ❌ Locked period allows mutation
- ❌ Transaction rollback fails (partial state corruption)

**These are system-fatal issues. Do not proceed until resolved.**

---

## ✅ PASS/FAIL Thresholds

**Acceptable Tolerances:**
- Monetary diff: ≤ 0.01
- Calculation time: < 5 seconds (for 100 entries)
- Deadlock retries: ≤ 3 attempts
- Hash mismatch: Must be detected (no silent failures)

**Test Result Criteria:**
- ✅ PASS: All validation checks pass within thresholds
- ❌ FAIL: Any validation check fails or exceeds threshold
- ⚠️ WARNING: Passes but with concerning behavior (document)

---

## 🧪 Execution Phases

### Phase A: Core Financial Integrity (RUN FIRST)

**Priority:** CRITICAL - If anything fails here, STOP everything

**Scenarios:** 1, 4, 5, 7, 10, 28, 27

**Purpose:** Validate calculation correctness, reversal handling, locking + linking, boundary correctness

**Dependencies:** Clean database state, no prior modifications

---

### Phase B: Locking & Period Safety

**Scenarios:** 2, 3, 6, 29

**Purpose:** Ensure no double payment, period isolation correct

**Dependencies:** Phase A must pass

---

### Phase C: Workflow Integrity

**Scenarios:** 11, 12, 5 (re-run), 30

**Purpose:** Validate status transitions, audit trail completeness

**Dependencies:** Phase A, B must pass

---

### Phase D: Recalculation & Drift Detection

**Scenarios:** 4 (re-run), 17, 16, 20

**Purpose:** This is where most systems break silently - focus on entries_hash, mismatch detection, recalculation diff

**Dependencies:** Phase A, B, C must pass

---

### Phase E: Idempotency & Concurrency

**Scenarios:** 8, 9, 21, 22, 25

**Purpose:** Validate race conditions, retry safety, duplicate prevention

**Dependencies:** Phase A, B must pass

---

### Phase F: Admin Controls (DESTRUCTIVE - RUN LATE)

**Scenarios:** 13, 14, 15

**Purpose:** Validate emergency override operations

**⚠️ WARNING:** These MODIFY state - do not run early

**Dependencies:** All previous phases must pass

---

### Phase G: Observability & Logs

**Scenarios:** 18, 19

**Purpose:** Validate log monitoring and audit trail visibility

**Dependencies:** Phase A, C must pass

---

### Phase H: Performance & Edge

**Scenarios:** 23, 24, 26

**Purpose:** Validate performance for large datasets, edge cases

**Dependencies:** Phase A must pass

---

## Test Scenario 1: Full Month Payment Creation

**Objective:** Create a payment request for a complete month with mixed credit/debit entries

**Steps:**
1. Create ledger entries for a machinery:
   - Credit: Reading payment (5000.00) on 2026-01-05
   - Credit: Diesel charge (2500.00) on 2026-01-10
   - Credit: Maintenance charge (1500.00) on 2026-01-15
   - Debit: Advance deduction (1000.00) on 2026-01-08
   - Debit: Transfer deduction (500.00) on 2026-01-20

2. Create payment request:
   - Machinery: [machinery_id]
   - Period: 2026-01-01 to 2026-01-31
   - Click "Calculate from Ledger"

**Expected Results:**
- Credits: 9000.00
- Debits: 1500.00
- Net Payable: 7500.00
- Entry Count: 5
- Status: draft

**Validation:**
- ✅ Calculation matches manual sum
- ✅ Audit snapshot includes all 5 entry IDs
- ✅ entries_hash is generated

---

## Test Scenario 2: Overlapping Period Blocking

**Objective:** Ensure overlapping payment requests are blocked

**Steps:**
1. Create payment request for period: 2026-01-15 to 2026-01-31
2. Submit the request (status: submitted)
3. Try to create another request for: 2026-01-20 to 2026-02-10

**Expected Results:**
- ❌ Second request blocked with error: "Active payment request already exists for this period"

**Validation:**
- ✅ Only one active request for overlapping period
- ✅ Error message is clear

---

## Test Scenario 3: Locked Period Blocking

**Objective:** Ensure locked periods block new requests

**Steps:**
1. Create payment request for period: 2026-01-01 to 2026-01-31
2. Submit → Verify → Approve (period becomes locked)
3. Try to create request for: 2026-01-15 to 2026-01-20

**Expected Results:**
- ❌ Request blocked with error: "Payment period overlaps with existing locked period"

**Validation:**
- ✅ Locked period prevents new requests
- ✅ machinery_payment_periods table shows is_locked = true

---

## Test Scenario 4: Backdated Entry Detection

**Objective:** Ensure recalculation detects backdated ledger entries

**Steps:**
1. Create payment request for period: 2026-01-01 to 2026-01-31
2. Submit the request
3. Add a new ledger entry with date: 2026-01-25 (backdated within period)
4. Click "Recalculate" button

**Expected Results:**
- ⚠️ Recalculation shows mismatch
- Original vs Current diff > 0.01
- has_mismatch: true
- can_approve: false

**Validation:**
- ✅ Recalculate endpoint returns correct diff
- ✅ Approval would be blocked
- ✅ UI shows warning

---

## Test Scenario 5: Approval and Period Locking

**Objective:** Verify approval locks period and links ledger entries

**Steps:**
1. Create payment request for period: 2026-01-01 to 2026-01-31
2. Submit → Verify → Approve

**Expected Results:**
- Status: approved
- machinery_payment_periods table: new row with is_locked = true
- machinery_ledger table: all 5 entries have payment_request_id set
- Audit log shows approval event with full details

**Validation:**
- ✅ Period locked in machinery_payment_periods
- ✅ Ledger entries linked to payment request
- ✅ Audit log contains: payment_request_id, ledger_entry_ids, entries_hash, net_payable

---

## Test Scenario 6: Locked Period Modification Blocking

**Objective:** Ensure locked periods cannot be modified

**Steps:**
1. Approve a payment request (period locked)
2. Try to add a new ledger entry within the locked period

**Expected Results:**
- ❌ Ledger entry creation blocked (if service has lock check)
- OR: Entry created but cannot be linked to payment request

**Validation:**
- ✅ System prevents or isolates modifications to locked periods
- ✅ Period lock consistency check in approval would fail

---

## Test Scenario 7: Negative Payable Handling

**Objective:** Ensure negative payable results in HOLD status

**Steps:**
1. Create only debit entries:
   - Debit: Advance (5000.00) on 2026-01-10
   - Debit: Transfer (3000.00) on 2026-01-15
2. Create payment request for period: 2026-01-01 to 2026-01-31

**Expected Results:**
- Credits: 0.00
- Debits: 8000.00
- Net Payable: -8000.00
- Status: hold (not draft)

**Validation:**
- ✅ Status automatically set to hold
- ✅ Audit log shows warning about negative payable

---

## Test Scenario 8: Idempotency - Duplicate Request

**Objective:** Ensure duplicate requests with same idempotency key return same record

**Steps:**
1. Generate idempotency_key: "test-key-123"
2. Create payment request with idempotency_key
3. Create another payment request with same idempotency_key

**Expected Results:**
- ✅ Second request returns same payment_request_id
- ✅ No duplicate records created
- ✅ Workspace-scoped idempotency works

**Validation:**
- ✅ Check database: only 1 record with that idempotency_key
- ✅ Response data matches first request

---

## Test Scenario 9: Idempotency - Conflict Handling

**Objective:** Ensure idempotency conflicts are handled gracefully

**Steps:**
1. Create payment request with idempotency_key
2. Manually delete the record
3. Try to create again with same idempotency_key

**Expected Results:**
- ✅ New request created successfully
- ✅ Idempotency key can be reused after deletion

**Validation:**
- ✅ Service handles missing records correctly
- ✅ No errors on retry

---

## Test Scenario 10: Double-Spend Prevention

**Objective:** Ensure ledger entries cannot be linked to multiple payment requests

**Steps:**
1. Create payment request for period: 2026-01-01 to 2026-01-31
2. Submit → Verify → Approve (entries linked)
3. Try to create another request for same period

**Expected Results:**
- ❌ Second request blocked (active request overlap)
- OR: If somehow created, approval blocked by double-spend check

**Validation:**
- ✅ Double-spend protection in approve() method
- ✅ Error: "Some entries already linked to another payment request"

---

## Test Scenario 11: Status Transition Guards

**Objective:** Ensure invalid status transitions are blocked

**Steps:**
1. Create payment request (status: draft)
2. Try to approve directly (skipping submit/verify)

**Expected Results:**
- ❌ Approval blocked with error: "Cannot approve request in status: draft"

**Validation:**
- ✅ All invalid transitions blocked
- ✅ Error message indicates current and target status

---

## Test Scenario 12: Rejection Flow

**Objective:** Ensure rejection works at any stage before lock

**Steps:**
1. Create payment request (status: draft)
2. Submit (status: submitted)
3. Reject with reason: "Incorrect calculation"

**Expected Results:**
- Status: rejected
- Remarks contains rejection reason
- Audit log shows rejection event

**Validation:**
- ✅ Rejection works from draft, submitted, verified
- ✅ Rejection blocked after locked/paid

---

## Test Scenario 13: Admin Force Reject

**Objective:** Ensure admin can force reject after approval

**Steps:**
1. Create payment request
2. Submit → Verify → Approve (status: approved)
3. Use admin control: Force Reject with reason: "Emergency override"

**Expected Results:**
- Status: rejected
- Remarks: "ADMIN OVERRIDE: Emergency override"
- Audit log shows admin_override event

**Validation:**
- ✅ Force reject bypasses normal transition guards
- ✅ Full audit trail of override

---

## Test Scenario 14: Admin Force Unlock

**Objective:** Ensure admin can unlock locked periods

**Steps:**
1. Create payment request
2. Submit → Verify → Approve (period locked)
3. Use admin control: Force Unlock with reason: "Correction needed"

**Expected Results:**
- machinery_payment_periods: is_locked = false
- machinery_ledger: payment_request_id = null for all entries
- Audit log shows force unlock event

**Validation:**
- ✅ Period unlocked
- ✅ Ledger entries unlinked
- ✅ New request can be created for same period

---

## Test Scenario 15: Admin Override Note

**Objective:** Ensure admin can add notes without changing amounts

**Steps:**
1. Create payment request
2. Use admin control: Add Override Note: "Reviewed by finance team"

**Expected Results:**
- Remarks updated with note
- No financial amounts changed
- Audit log shows note addition

**Validation:**
- ✅ Note added to remarks
- ✅ credits/debits/net_payable unchanged

---

## Test Scenario 16: Debug Endpoint

**Objective:** Ensure debug endpoint provides useful information

**Steps:**
1. Create payment request
2. Call GET /api/machinery/payment-requests/{id}/debug

**Expected Results:**
- Returns: payment_request details
- Returns: calculation_snapshot (original)
- Returns: current_calculation (recalculated)
- Returns: calculation_mismatch (boolean flags)
- Returns: ledger_entries (sample)

**Validation:**
- ✅ All expected fields present
- ✅ Mismatch detection works
- ✅ Sample entries show relevant data

---

## Test Scenario 17: Recalculate Endpoint

**Objective:** Ensure recalculate shows diff clearly

**Steps:**
1. Create payment request
2. Add backdated ledger entry
3. Call GET /api/machinery/payment-requests/{id}/recalculate

**Expected Results:**
- Returns: original (credits, debits, net_payable)
- Returns: current (recalculated values)
- Returns: diff (credits_diff, debits_diff, net_payable_diff)
- Returns: has_mismatch (boolean)
- Returns: can_approve (boolean)

**Validation:**
- ✅ Diff calculation correct
- ✅ has_mismatch flag accurate
- ✅ can_approve flag accurate

---

## Test Scenario 18: Log Monitoring - Filter by Payment Request

**Objective:** Ensure logs can be filtered by payment_request_id

**Steps:**
1. Create and approve a payment request
2. Call GET /api/machinery/payment-logs?payment_request_id={id}

**Expected Results:**
- Returns all log lines containing the payment_request_id
- Includes: creation, submission, verification, approval events
- Channel: payment_audit

**Validation:**
- ✅ Relevant logs returned
- ✅ No unrelated logs included
- ✅ Log count reasonable

---

## Test Scenario 19: Log Monitoring - Recent Machinery Logs

**Objective:** Ensure recent machinery logs are accessible

**Steps:**
1. Perform several machinery payment operations
2. Call GET /api/machinery/payment-logs/recent

**Expected Results:**
- Returns logs from payment_audit channel
- Returns logs from payment_debug channel
- Filtered for machinery-related entries
- Last 50 lines by default

**Validation:**
- ✅ Both channels included
- ✅ Machinery filter works
- ✅ Recent logs shown

---

## Test Scenario 20: Audit Hash Integrity

**Objective:** Ensure entries_hash detects tampering

**Steps:**
1. Create payment request
2. Note the entries_hash from audit_snapshot
3. Manually modify a ledger entry amount in database
4. Call debug endpoint
5. Check hash_mismatch flag

**Expected Results:**
- hash_mismatch: true
- Debug endpoint shows hash mismatch
- Recalculation shows amount mismatch

**Validation:**
- ✅ Hash detects tampering
- ✅ Tampering clearly visible in debug output

---

## Test Scenario 21: Concurrency - Parallel Request Creation

**Objective:** Ensure parallel requests don't create duplicates

**Steps:**
1. Use Postman Runner to send 5 identical requests simultaneously
2. Same machinery, period, idempotency_key
3. Check database for duplicates

**Expected Results:**
- Only 1 payment request created
- Other 4 return existing record (idempotency)
- OR: Only 1 succeeds, others fail with unique constraint

**Validation:**
- ✅ No duplicate payment requests
- ✅ Idempotency or database constraint prevents duplicates

---

## Test Scenario 22: Concurrency - Parallel Approval

**Objective:** Ensure parallel approvals don't cause issues

**Steps:**
1. Create payment request (status: verified)
2. Use Postman Runner to send 5 approve requests simultaneously
3. Check ledger entry linking

**Expected Results:**
- Only 1 approval succeeds
- Ledger entries linked only once
- No double-linking

**Validation:**
- ✅ Deadlock retry handles concurrency
- ✅ Ledger entries linked to single payment request
- ✅ No inconsistent state

---

## Test Scenario 23: Empty Period Handling

**Objective:** Ensure empty periods are handled correctly

**Steps:**
1. Create payment request for period with no ledger entries
2. Try to calculate

**Expected Results:**
- ❌ Error: "No eligible ledger entries found for the specified period"
- OR: Request created with 0 credits, 0 debits, 0 net_payable

**Validation:**
- ✅ System handles empty periods gracefully
- ✅ Clear error message

---

## Test Scenario 24: Large Entry Count Performance

**Objective:** Ensure system handles large entry counts

**Steps:**
1. Create 100+ ledger entries for a period
2. Create payment request
3. Check calculation time

**Expected Results:**
- Calculation completes in reasonable time (< 5 seconds)
- All entries included in snapshot
- Audit snapshot not truncated

**Validation:**
- ✅ Performance acceptable
- ✅ All entries accounted for
- ✅ No memory issues

---

## Test Scenario 25: Workspace Isolation

**Objective:** Ensure idempotency is workspace-scoped

**Steps:**
1. Create payment request in Workspace A with idempotency_key "test-123"
2. Create payment request in Workspace B with same idempotency_key "test-123"

**Expected Results:**
- Both requests created successfully
- No idempotency conflict across workspaces
- Unique constraint on (workspace_id, idempotency_key) works

**Validation:**
- ✅ Workspace isolation works
- ✅ Same key can be used in different workspaces

---

## Test Scenario 26: Supplier Change After Creation

**Objective:** Ensure supplier change doesn't affect calculation

**Steps:**
1. Create payment request for Supplier A
2. Change supplier in database to Supplier B
3. Recalculate

**Expected Results:**
- Calculation unchanged (based on ledger, not supplier)
- Supplier field is metadata only
- No financial impact

**Validation:**
- ✅ Supplier change doesn't affect payable
- ✅ Supplier is informational only

---

## Test Scenario 27: Period Boundary Edge Cases

**Objective:** Ensure date boundaries are handled correctly

**Steps:**
1. Create ledger entry on 2026-01-31
2. Create payment request for 2026-01-01 to 2026-01-31
3. Check if entry included

**Expected Results:**
- Entry on last day included (inclusive boundary)
- Entry on first day included (inclusive boundary)

**Validation:**
- ✅ Inclusive boundaries work correctly
- ✅ No off-by-one errors

---

## Test Scenario 28: Reversal Entry Handling

**Objective:** Ensure reversal entries are excluded

**Steps:**
1. Create credit entry
2. Create reversal entry (is_reversal = true)
3. Create payment request

**Expected Results:**
- Only original entry included
- Reversal entry excluded
- Calculation correct

**Validation:**
- ✅ Reversal entries filtered out
- ✅ Net calculation correct

---

## Test Scenario 29: Partial Period After Approval

**Objective:** Ensure partial periods can be created after full approval

**Steps:**
1. Create and approve payment for 2026-01-01 to 2026-01-31
2. Create payment for 2026-02-01 to 2026-02-15

**Expected Results:**
- Second request created successfully
- No overlap with locked period
- Periods don't conflict

**Validation:**
- ✅ Non-overlapping periods work
- ✅ Adjacent periods allowed

---

## Test Scenario 30: Audit Trail Completeness

**Objective:** Ensure complete audit trail for all operations

**Steps:**
1. Perform full workflow: create → submit → verify → approve → lock → pay
2. Check logs for all events
3. Verify audit_snapshot at each stage

**Expected Results:**
- Log entry for each state transition
- Audit snapshot preserved at creation
- Approval log includes full details

**Validation:**
- ✅ Complete audit trail
- ✅ All critical events logged
- ✅ Audit reproducibility maintained

---

## Test Scenario 31: Partial Failure During Approval (CRITICAL)

**Objective:** Ensure transaction rollback works if approval fails mid-operation

**Steps:**
1. Create payment request (status: verified)
2. Simulate exception after period lock (modify service to throw after lockPeriod())
3. Attempt approval

**Expected Results:**
- ❌ Approval fails with exception
- machinery_payment_periods: NO new row (rollback)
- machinery_ledger: NO payment_request_id set (rollback)
- No partial state corruption

**Validation:**
- ✅ DB::transaction() wraps BOTH period lock and ledger update
- ✅ Rollback removes partial changes
- ✅ System returns to clean state on failure
- ✅ No orphaned locked periods

**Code Check:**
```php
// Verify this structure in MachineryPaymentRequestService::approve()
$this->safeTransaction(function () use ($request, $userId, $from, $to) {
    $this->lockPeriod($request, $userId);  // Step 1
    // ... validation ...
    MachineryLedger::whereIn('id', $ledgerEntryIds)  // Step 2
        ->whereNull('payment_request_id')
        ->update(['payment_request_id' => $request->id]);
    $request->update(['status' => $to->value, ...]);  // Step 3
});
```

---

## Test Scenario 32: Ledger Mutation Between Verify → Approve (CRITICAL)

**Objective:** Ensure approval fails if ledger changes after verification

**Steps:**
1. Create payment request (status: verified)
2. User A clicks verify (status: verified)
3. User B inserts new ledger entry within the period (backdated)
4. User A clicks approve

**Expected Results:**
- ❌ Approval blocked with error: "Ledger calculation has changed since payment request was created"
- Status remains: verified
- No period lock created
- No ledger entries linked

**Validation:**
- ✅ reverifyCalculation() called in verify() and approve()
- ✅ Mismatch detected and blocks approval
- ✅ Original calculation preserved
- ✅ No financial corruption possible

**Code Check:**
```php
// Verify this is called in both verify() and approve()
private function reverifyCalculation(MachineryPaymentRequest $request): void
{
    // Recalculate from ledger
    // Compare with snapshot
    // Block if mismatch
}
```

---

## Test Scenario 33: Same-Day Multi-Entry Ordering (CRITICAL)

**Objective:** Ensure deterministic ordering when multiple entries have same date

**Steps:**
1. Create 5 ledger entries all on 2026-01-15:
   - Entry 1: Credit, amount 1000, id 1
   - Entry 2: Credit, amount 2000, id 2
   - Entry 3: Debit, amount 500, id 3
   - Entry 4: Credit, amount 1500, id 4
   - Entry 5: Debit, amount 300, id 5
2. Create payment request for period including 2026-01-15
3. Check which entries are included

**Expected Results:**
- All 5 entries included (same date, all within period)
- Order is deterministic (ORDER BY date, id)
- Calculation consistent across multiple runs
- Running balance calculation correct

**Validation:**
- ✅ ORDER BY date, id ensures deterministic ordering
- ✅ All same-day entries included
- ✅ No off-by-one errors in date boundaries
- ✅ Backdated insert with same date handled correctly

**Code Check:**
```php
// Verify this ordering in lockLedgerEntries()
->orderBy('date')  // Primary sort
->orderBy('id')    // Secondary sort for same date
```

---

## Test Scenario 34: Same Entry Updated After Snapshot (CRITICAL)

**Objective:** Ensure immutability assumption holds - modifying existing entry is detected

**Steps:**
1. Create payment request with ledger entries
2. Note the entries_hash from audit_snapshot
3. Modify amount of an existing ledger entry (same ID, different amount) in database
4. Call recalculate endpoint
5. Attempt approval

**Expected Results:**
- hash_mismatch = true
- recalculation mismatch = true (amount changed)
- Approval blocked with error
- entries_hash detects the change

**Validation:**
- ✅ entries_hash detects ANY change to existing entries
- ✅ Recalculation shows amount mismatch
- ✅ Approval blocked before period lock
- ✅ Immutability assumption validated

**Code Check:**
```php
// Verify hash includes immutable fields only
hash('sha256', json_encode($entries->map(fn($e) => [
    'id' => $e->id,
    'date' => $e->date,
    'amount' => $e->amount,
    'entry_direction' => $e->entry_direction,
    'entry_type' => $e->entry_type,
])->toArray()))
```

---

## Phase-Based Execution Checklist

### Phase A: Core Financial Integrity (RUN FIRST)
- [ ] Scenario 1: Full Month Payment Creation
- [ ] Scenario 4: Backdated Entry Detection
- [ ] Scenario 5: Approval and Period Locking
- [ ] Scenario 7: Negative Payable Handling
- [ ] Scenario 10: Double-Spend Prevention
- [ ] Scenario 28: Reversal Entry Handling
- [ ] Scenario 27: Period Boundary Edge Cases

**STOP if any fail. These are system-fatal.**

### Phase B: Locking & Period Safety
- [ ] Scenario 2: Overlapping Period Blocking
- [ ] Scenario 3: Locked Period Blocking
- [ ] Scenario 6: Locked Period Modification Blocking
- [ ] Scenario 29: Partial Period After Approval

### Phase C: Workflow Integrity
- [ ] Scenario 11: Status Transition Guards
- [ ] Scenario 12: Rejection Flow
- [ ] Scenario 5: Approval and Period Locking (re-run)
- [ ] Scenario 30: Audit Trail Completeness

### Phase D: Recalculation & Drift Detection
- [ ] Scenario 4: Backdated Entry Detection (re-run)
- [ ] Scenario 17: Recalculate Endpoint
- [ ] Scenario 16: Debug Endpoint
- [ ] Scenario 20: Audit Hash Integrity

### Phase E: Idempotency & Concurrency
- [ ] Scenario 8: Idempotency - Duplicate Request
- [ ] Scenario 9: Idempotency - Conflict Handling
- [ ] Scenario 21: Concurrency - Parallel Request Creation
- [ ] Scenario 22: Concurrency - Parallel Approval
- [ ] Scenario 25: Workspace Isolation

### Phase F: Admin Controls (DESTRUCTIVE - RUN LAST)
- [ ] Scenario 13: Admin Force Reject
- [ ] Scenario 14: Admin Force Unlock
- [ ] Scenario 15: Admin Override Note

**⚠️ These modify state - run only after all other phases pass**

### Phase G: Observability & Logs
- [ ] Scenario 18: Log Monitoring - Filter by Payment Request
- [ ] Scenario 19: Log Monitoring - Recent Machinery Logs

### Phase H: Performance & Edge
- [ ] Scenario 23: Empty Period Handling
- [ ] Scenario 24: Large Entry Count Performance
- [ ] Scenario 26: Supplier Change After Creation

### Critical Scenarios (Add to Phase A)
- [ ] Scenario 31: Partial Failure During Approval
- [ ] Scenario 32: Ledger Mutation Between Verify → Approve
- [ ] Scenario 33: Same-Day Multi-Entry Ordering
- [ ] Scenario 34: Same Entry Updated After Snapshot

---

## � Final Pre-Execution Lock (VERIFY BEFORE RUNNING)

**Confirm these in code before running Scenario 1:**

### 1. Approval Flow Order (CRITICAL)

Inside `approve()` method:
```php
// CORRECT ORDER (verified):
$this->reverifyCalculation($request);  // Step 1: Validate calculation
$this->withDeadlockRetry(function () use ($request, $userId, $from, $to) {
    $this->safeTransaction(function () use ($request, $userId, $from, $to) {
        $this->lockPeriod($request, $userId);  // Step 2: Lock period
        // ... link ledger, update status ...
    });
});
```

**Status:** ✅ VERIFIED - `reverifyCalculation()` is called BEFORE transaction starts

**Risk if wrong:** Locking corrupted state before validation

### 2. Ledger Query Filters (CRITICAL)

Inside `lockLedgerEntries()` method:
```php
// REQUIRED (all verified):
return MachineryLedger::where('machinery_id', $machineryId)
    ->whereBetween('date', [$periodStart, $periodEnd])
    ->where('is_reversal', false)           // ✅ VERIFIED
    ->whereNull('payment_request_id')      // ✅ VERIFIED - Only unpaid
    ->orderBy('date')                      // ✅ VERIFIED
    ->orderBy('id')                        // ✅ VERIFIED
    ->lockForUpdate()                      // ✅ VERIFIED
    ->get();
```

**Status:** ✅ ALL FILTERS PRESENT - Missing any = silent corruption risk

### 3. Transaction Boundary (CRITICAL)

Everything inside `safeTransaction()`:
```php
$this->safeTransaction(function () use ($request, $userId, $from, $to) {
    $this->lockPeriod($request, $userId);
    // ... double-spend check ...
    MachineryLedger::whereIn('id', $ledgerEntryIds)
        ->update(['payment_request_id' => $request->id]);
    // ... period lock consistency check ...
    $request->update(['status' => $to->value, ...]);
});
```

**Status:** ✅ VERIFIED - No nested commits, no external writes outside transaction

**⚠️ All pre-execution locks verified. Proceed to Phase A execution.**

---

## � Phase A Execution Protocol (Refined)

**DO NOT run tests blindly from UI.** Run like a forensic audit.

### Step 1: Freeze Environment

Before Phase A execution:
- Clear DB (or use fresh workspace)
- Disable background jobs / cron
- Use single machinery_id for all Phase A tests
- Enable verbose logging (payment_debug channel)

### 🟡 Golden Rule

**1 Scenario = 1 Clean State Block**

**DO NOT:**
- Reuse dirty data
- "Continue from previous scenario"

**DO:**
- Reset or isolate if scenario modifies core state
- Capture baseline before each scenario

### 🏷️ Naming Convention (IMPORTANT)

While testing, name everything clearly:

```
machinery_id = 1 (use single machinery for all Phase A)
idempotency_key = phaseA-s1, phaseA-s2, etc.
```

This helps trace logs + DB instantly.

### ⚡ Scenario Execution Order (Optimized)

Run Phase A in this exact order:

**Round 1: Baseline Stability**
1. Scenario 1 → Full calculation
2. Scenario 7 → Negative payable
3. Scenario 1 (re-run) → Re-run baseline

**Purpose:** Confirms stability

**Round 2: Selection Correctness**
4. Scenario 27 (boundary)
5. Scenario 28 (reversal)
6. Scenario 33 (ordering)

**Purpose:** Validates selection correctness

**Round 3: Drift Detection**
7. Scenario 4 (backdated)
8. Scenario 34 (immutability)

**Purpose:** Validates drift detection

**Round 4: Ownership + Locking**
9. Scenario 5 (approval)
10. Scenario 10 (double-spend)

**Purpose:** Validates ownership + locking

**Round 5: System Safety (CRITICAL)**
11. Scenario 31 (rollback)
12. Scenario 32 (mutation during approval)

**Purpose:** Validates system safety under failure

---

### Step 2: Per-Scenario Validation (Non-Negotiable)

For EACH scenario in Phase A:

**1. Capture Baseline (BEFORE action)**
```sql
SELECT COUNT(*) FROM machinery_payment_requests;
SELECT COUNT(*) FROM machinery_payment_periods;
SELECT COUNT(*) FROM machinery_ledger WHERE payment_request_id IS NOT NULL;
```
This gives you a clean delta check later.

**2. Execute Scenario**
- Use UI for realism
- Use API for precision (especially concurrency scenarios)

**3. Immediate DB Verification**

A. Payment Request:
```sql
SELECT id, status, net_payable, entries_hash
FROM machinery_payment_requests
ORDER BY id DESC LIMIT 1;
```

B. Ledger Linking:
```sql
SELECT id, payment_request_id
FROM machinery_ledger
WHERE payment_request_id = {request_id};
```

C. Period Lock:
```sql
SELECT *
FROM machinery_payment_periods
WHERE machinery_id = {machinery_id}
ORDER BY id DESC;
```

**4. API Validation**
- GET `/api/machinery/payment-requests/{id}/debug`
- GET `/api/machinery/payment-requests/{id}/recalculate`

Validating:
- snapshot integrity
- drift detection
- hash correctness

**5. Log Validation**
Check BOTH channels:
- payment_audit
- payment_debug

Confirm:
- event order is correct
- no silent failures
- no retries > threshold

### Step 3: Critical Scenario Watch Points

**🔴 Scenario 4 + 32 (MOST IMPORTANT) - Backdated + Mutation**

Must confirm:
- Approval is blocked BEFORE:
  - period lock
  - ledger linking
- If lock happens before validation → data corruption risk

**🔴 Scenario 31 (Rollback)**

After failure, verify:
```sql
SELECT * FROM machinery_payment_periods ORDER BY id DESC LIMIT 1;
```
- Must NOT contain new locked period
```sql
SELECT COUNT(*) FROM machinery_ledger WHERE payment_request_id = {request_id};
```
- Must be 0
- If even 1 record persists → transaction boundary is broken

**🔴 Scenario 32 (Mutation Between Verify → Approve)**

Verify THIS specifically:
- Status remains verified
- No row in machinery_payment_periods
- No ledger linking
- If even 1 entry linked → critical bug

**🔴 Scenario 34 (Immutability)**

After modifying ledger:
- /debug must show: hash_mismatch = true
- If hash mismatch NOT detected → entire audit system is compromised

**🔴 Scenario 33 (Deterministic Ordering)**

Run calculation:
- 2 times before change
- 2 times after inserting same-date entry
- Results must be:
  - identical per state
  - predictable after change
- If inconsistent → race/order bug

---

## 🔍 Real Failure Patterns to Watch (Critical)

These are real bugs that appear even in good systems:

### ⚠️ Bug 1: "Phantom Ledger Inclusion"

**Symptom:** Entry appears in recalculation but not in snapshot

**Cause:** Inconsistent query filters

**Check:** Same WHERE clause everywhere in lockLedgerEntries, reverifyCalculation, and debug endpoint

### ⚠️ Bug 2: "Silent Partial Approval"

**Symptom:** Status = approved, but ledger not fully linked

**Means:** Transaction boundary broken or exception swallowed

**Check:** Run this after Scenario 5 (approval):
```sql
SELECT COUNT(*) 
FROM machinery_ledger
WHERE payment_request_id IS NULL
AND date BETWEEN '{period_start}' AND '{period_end}'
AND machinery_id = {machinery_id};
```
- Should be 0 for that period

### ⚠️ Bug 3: "Hash Looks Correct but Isn't"

**Symptom:** Hash doesn't change after ledger update

**Means:** Hash not using all fields or ordering not deterministic

**Check:** Verify debug endpoint shows hash_mismatch = true after modification

### ⚠️ Bug 4: "Race Between Verify and Approve"

**Symptom:** Verify passes, approve passes, but ledger changed in between

**Means:** `reverifyCalculation()` missing in `approve()`

**Check:** Already verified in code (line 317 before transaction)

---

## ⚠️ Hidden Risk: FLOAT/DECIMAL Drift

Check this during Scenario 1 & 4:
```sql
SELECT SUM(amount) FROM machinery_ledger
WHERE machinery_id = {machinery_id}
AND date BETWEEN '{period_start}' AND '{period_end}';
```

Compare with:
- Stored net_payable in payment request

If mismatch > 0.01:
- Rounding issue
- Aggregation inconsistency
- Must be investigated and fixed

---

## 🧾 Evidence Capture Template

Create a log file or sheet for each scenario:

| Scenario | Status | DB Verified | API Verified | Logs Checked | Notes |
|----------|--------|-------------|--------------|--------------|-------|
| 1        |        |             |              |              |       |
| 4        |        |             |              |              |       |
| 5        |        |             |              |              |       |
| ...      |        |             |              |              |       |

Without this, you'll forget what actually passed.

---

## 🚫 Common Mistakes During Execution

Avoid these:
- ❌ Running multiple scenarios without resetting state
- ❌ Trusting UI without DB verification
- ❌ Ignoring logs ("it worked" ≠ safe)
- ❌ Skipping re-runs (Scenario 4, 33 must be repeated)
- ❌ Continuing after critical failure
- ❌ Not capturing baseline before each scenario

---

## 🧪 Concurrency Pre-Test (Before Phase E)

Even in Phase A, simulate quick double-click approve:

**Test:** Click approve twice rapidly

**Expected:**
- Only one succeeds
- No duplicate logs
- No double linking

**Check:**
```sql
SELECT id, COUNT(*) as link_count
FROM machinery_ledger
WHERE payment_request_id = {request_id}
GROUP BY id;
```
- All counts should be 1

---

## ✅ What "Phase A Pass" REALLY Means

Not just:
- ✔ calculations correct
- ✔ API working

But:
- ✔ rollback proven (Scenario 31)
- ✔ mutation blocked (Scenario 32)
- ✔ hash detects tampering (Scenario 34)
- ✔ ordering deterministic (Scenario 33)
- ✔ no double spend possible (Scenario 10)
- ✔ decimal precision validated (Scenarios 1, 4)

### 🧪 When Phase A is "TRULY PASSED"

Not when tests pass...

But when:
**You CANNOT break it intentionally**

**Try to break:**
- Insert backdated entry → approval blocked ✅
- Update ledger → hash detects ✅
- Approve twice → only one succeeds ✅
- Simulate failure → rollback clean ✅

If system resists all attempts → you're ready for Phase B

---

## 🟢 Final Direction

You are now at:
👉 Financial Integrity Certification Stage

If Phase A passes clean:
- Your backend is production-grade
- Phase 3 UI becomes low-risk

If Phase A fails:
- Fix → restart Phase A (no shortcuts)

---

## 🧪 Architectural Invariants Validation

**After Phase A passes, confirm these invariants:**

### Invariant 1: Ledger is Single Source of Truth
- ✅ No manual override affects calculation
- ✅ All calculations derived from ledger entries only
- ✅ UI cannot influence payable amount

### Invariant 2: Snapshot is Immutable
- ✅ entries_hash detects ANY change
- ✅ Modification of existing entries detected
- ✅ New entries within period detected
- ✅ No silent calculation drift

### Invariant 3: Approval is Atomic
- ✅ lock + link + status → single transaction
- ✅ Partial failure results in complete rollback
- ✅ No orphaned locked periods
- ✅ No partially linked ledger entries

### Invariant 4: No Double Ownership
- ✅ Ledger entry belongs to only ONE request
- ✅ Double-spend protection prevents linking
- ✅ payment_request_id unique constraint enforced

---

## ⚠️ Strict Stop Conditions

**If ANY of these fail, STOP Phase A immediately:**

- ❌ Scenario 31 (rollback) - transaction boundary broken
- ❌ Scenario 32 (mutation detection) - approval not blocked
- ❌ Scenario 10 (double spend) - entries linked to multiple requests
- ❌ Scenario 34 (immutability) - hash mismatch not detected

**DO NOT:**
- Continue testing
- Patch later
- "Note it down"

**DO:**
- Fix the issue
- Restart Phase A from scratch
- Re-validate all scenarios

---

## Success Criteria

**Before Phase 3 (Full Production UI):**

- ✅ Phase A (Core Financial Integrity): 100% pass
- ✅ Phase B (Locking & Period Safety): 100% pass
- ✅ Phase C (Workflow Integrity): 100% pass
- ✅ Phase D (Recalculation & Drift Detection): 100% pass
- ✅ Phase E (Idempotency & Concurrency): 100% pass
- ✅ Phase F (Admin Controls): 100% pass
- ✅ Phase G (Observability & Logs): 100% pass
- ✅ Phase H (Performance & Edge): 100% pass
- ✅ No Red Flag conditions triggered
- ✅ All PASS/FAIL thresholds met
- ✅ Critical scenarios 31-33 validated

**Phase 3 Readiness:**

- ✅ System is battle-tested
- ✅ Real-world usage patterns understood
- ✅ Audit safety verified
- ✅ Emergency procedures tested
- ✅ Transaction rollback confirmed
- ✅ Ledger mutation detection confirmed
- ✅ Deterministic ordering confirmed
