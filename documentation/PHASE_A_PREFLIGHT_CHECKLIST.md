# Phase A Pre-Flight Checklist

**Status:** ✅ VERIFIED AND FIXED - Ready for Phase A Execution

**Date:** April 28, 2026

---

## 🔴 Critical Fixes Applied

### Fix 1: Hash Ordering Bug (DEBUG ENDPOINT)

**Issue:** Debug endpoint didn't sort entries before hashing

**Fix Applied:**
```php
// Debug endpoint now sorts before hashing
$ledgerEntries = MachineryLedger::whereIn('id', $ledgerEntryIds)
    ->where('is_reversal', false)
    ->orderBy('date')  // ✅ ADDED
    ->orderBy('id')    // ✅ ADDED
    ->get();

$sortedEntries = $ledgerEntries->sortBy(['date', 'id']);  // ✅ ADDED
$currentHash = hash('sha256', json_encode($sortedEntries->map(...)));
```

**Location:** `MachineryPaymentRequestController::debug()`

### Fix 2: Hash Ordering Bug (SNAPSHOT CREATION)

**Issue:** buildAuditSnapshot didn't explicitly sort before hashing

**Fix Applied:**
```php
// buildAuditSnapshot now explicitly sorts
$sortedEntries = $entries->sortBy(['date', 'id']);  // ✅ ADDED
$entriesHash = hash('sha256', json_encode($sortedEntries->map(...)));
```

**Location:** `MachineryPaymentRequestService::buildAuditSnapshot()`

### Fix 3: Query Consistency (REVERIFY CALCULATION)

**Issue:** reverifyCalculation missing orderBy clauses

**Fix Applied:**
```php
$entries = MachineryLedger::whereIn('id', $ledgerEntryIds)
    ->where('is_reversal', false)
    ->orderBy('date')  // ✅ ADDED
    ->orderBy('id')    // ✅ ADDED
    ->get();
```

**Location:** `MachineryPaymentRequestService::reverifyCalculation()`

### Fix 4: Query Consistency (RECALCULATE ENDPOINT)

**Issue:** recalculate endpoint missing orderBy clauses

**Fix Applied:**
```php
$ledgerEntries = MachineryLedger::whereIn('id', $ledgerEntryIds)
    ->where('is_reversal', false)
    ->orderBy('date')  // ✅ ADDED
    ->orderBy('id')    // ✅ ADDED
    ->get();
```

**Location:** `MachineryPaymentRequestController::recalculate()`

---

## ✅ Query Parity Verification

### lockLedgerEntries() (Creation Query)
```php
MachineryLedger::where('machinery_id', $machineryId)
    ->whereBetween('date', [$periodStart, $periodEnd])
    ->where('is_reversal', false)           // ✅
    ->whereNull('payment_request_id')      // ✅
    ->orderBy('date')                      // ✅
    ->orderBy('id')                        // ✅
    ->lockForUpdate()                      // ✅
    ->get();
```

### reverifyCalculation() (Verification Query)
```php
MachineryLedger::whereIn('id', $ledgerEntryIds)
    ->where('is_reversal', false)           // ✅
    ->orderBy('date')                      // ✅ ADDED
    ->orderBy('id')                        // ✅ ADDED
    ->get();
```

### debug() / recalculate() (API Queries)
```php
MachineryLedger::whereIn('id', $ledgerEntryIds)
    ->where('is_reversal', false)           // ✅
    ->orderBy('date')                      // ✅ ADDED
    ->orderBy('id')                        // ✅ ADDED
    ->get();
```

---

## ✅ Approval Flow Verification

```php
public function approve(int $paymentRequestId, int $userId): void
{
    // Step 1: Validate calculation BEFORE transaction
    $this->reverifyCalculation($request);  // ✅ VERIFIED - Line 317
    
    $this->withDeadlockRetry(function () use (...) {
        $this->safeTransaction(function () use (...) {
            // Step 2: Lock period
            $this->lockPeriod($request, $userId);
            
            // Step 3: Double-spend check
            // Step 4: Link ledger
            // Step 5: Consistency check
            // Step 6: Update status
        });
    });
}
```

**Critical Order:**
1. `reverifyCalculation()` - OUTSIDE transaction (validates before locking)
2. `lockPeriod()` - INSIDE transaction (creates locked period)
3. Link ledger - INSIDE transaction
4. Update status - INSIDE transaction

---

## ✅ Transaction Boundary Verification

All critical operations inside `safeTransaction()`:
- ✅ Period lock creation
- ✅ Double-spend validation
- ✅ Ledger entry linking
- ✅ Period lock consistency check
- ✅ Status update

No external writes outside transaction.

---

## ✅ Hash Field Consistency

**Hashed Fields (All Locations):**
- id
- date
- amount
- entry_direction
- entry_type

**NOT Hashed (Correctly Excluded):**
- running_balance (derived)
- updated_at (changes)
- metadata (evolving)
- payment_request_id (contextual)

---

## ✅ Pre-Execution Status

| Check | Status |
|-------|--------|
| Approval flow order | ✅ VERIFIED |
| Ledger query filters | ✅ VERIFIED |
| Transaction boundary | ✅ VERIFIED |
| Query parity | ✅ VERIFIED + FIXED |
| Hash ordering | ✅ VERIFIED + FIXED |
| Hash field consistency | ✅ VERIFIED |

---

## 🚀 Ready for Phase A Execution

**All critical issues resolved. Proceed with Phase A testing using the forensic audit protocol.**

**Execute scenarios in order:**
1. Round 1: Baseline Stability (1 → 7 → 1)
2. Round 2: Selection Correctness (27 → 28 → 33)
3. Round 3: Drift Detection (4 → 34)
4. Round 4: Ownership + Locking (5 → 10)
5. Round 5: System Safety (31 → 32)

**Remember:**
- Stop immediately if any Red Flag condition triggers
- Capture baseline before each scenario
- Verify DB state after each scenario
- Check logs carefully

---

**Documentation:** MACHINERY_PAYMENT_TEST_SCENARIOS.md
