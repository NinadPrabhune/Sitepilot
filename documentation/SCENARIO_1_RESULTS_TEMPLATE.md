# Scenario 1 Results Template

**Submit these results after completing Scenario 1 execution.**

---

## 1. BASELINE COUNTS (Before Start)

```
Payment Requests: ___
Payment Periods: ___
Linked Ledger: ___
```

---

## 2. MICRO-CHECKS (5 Critical Checks)

| Check | Expected | Actual | Pass/Fail |
|-------|----------|--------|-----------|
| Existing entries | 0 | ___ | ___ |
| Reversal entries | 0 | ___ | ___ |
| Duplicates | none | ___ | ___ |
| Workspace contamination | 0 | ___ | ___ |
| Timezone drift | none | ___ | ___ |

**Micro-Checks Status:** ___ (ALL PASS / SOME FAIL)

---

## 3. PAYMENT REQUEST ROW (After Creation)

```sql
SELECT id, status, credits, debits, net_payable, entries_hash
FROM machinery_payment_requests
ORDER BY id DESC LIMIT 1;
```

**Results:**
- ID: ___
- Status: ___
- Credits: ___
- Debits: ___
- Net Payable: ___
- Entries Hash: ___ (copy full value)

---

## 4. DEBUG ENDPOINT - Critical Fields Only

**Call:** `GET /api/machinery/payment-requests/{id}/debug`

**Record:**
- `ledger_entries.count`: ___
- `ledger_entries.ids`: ___ (first 5 IDs in order)
- `calculation_mismatch.hash_mismatch`: ___
- `calculation_mismatch.net_payable_mismatch`: ___
- `current_calculation.entries_hash`: ___ (first 10 chars)

**Order Check:** IDs sorted by date, id? ___ (yes/no)

---

## 5. RECALCULATE ENDPOINT

**Call:** `GET /api/machinery/payment-requests/{id}/recalculate`

**Record:**
- `original.net_payable`: ___
- `current.net_payable`: ___
- `diff.net_payable`: ___
- `has_mismatch`: ___
- `can_approve`: ___

---

## 6. HASH STABILITY TEST (3 Calls)

| Call | Hash (first 10 chars) | Match Call 1? |
|------|------------------------|---------------|
| Call 1 (initial) | ___ | - |
| Call 2 (after 2-3s delay) | ___ | ___ |
| Call 3 (after updated_at change) | ___ | ___ |

**Hash Stability:** ___ (ALL MATCH / SOME DIFFER)

---

## 7. RESISTANCE TEST RESULTS

### After +1.00 Amount Modification
- `debug.hash_mismatch`: ___ (expected: true)
- `recalculate.has_mismatch`: ___ (expected: true)
- `recalculate.diff`: ___ (expected: 1.00)
- `recalculate.can_approve`: ___ (expected: false)
- UI blocks approval? ___ (expected: yes)

### After entry_type Modification
- Hash detects change? ___ (expected: yes)

### After Revert (Restore Amount)
- Hash restored to original? ___ (expected: yes)
- `recalculate.has_mismatch`: ___ (expected: false)
- `recalculate.can_approve`: ___ (expected: true)
- `recalculate.diff`: ___ (expected: 0)

**Resistance Test Status:** ___ (ALL PASS / SOME FAIL)

---

## 8. CRITICAL VERIFICATION QUERY

```sql
SELECT COUNT(*) FROM machinery_ledger WHERE payment_request_id IS NOT NULL;
```

**Result:** ___ (expected: 0)

---

## 9. LOGS VERIFICATION

**Call:** `GET /api/machinery/payment-logs?payment_request_id={id}`

**Events Found:**
- CREATE: ___ (yes/no)
- CALCULATION: ___ (yes/no)
- VERIFY: ___ (should be NO at this stage)
- APPROVE: ___ (should be NO at this stage)

**Sequence Correct:** ___ (yes/no)

---

## 10. ENTRY SET EQUALITY

**SQL Count:** ___ (expected: 5)
**Snapshot Count:** ___ (expected: 5)
**Match:** ___ (yes/no)

---

## 📊 FINAL ASSESSMENT

### Critical Checks Summary

| Category | Checks | Passed | Failed |
|----------|--------|--------|--------|
| Micro-Checks | 5 | ___ | ___ |
| Calculations | 6 | ___ | ___ |
| Source Verification | 3 | ___ | ___ |
| DB State | 4 | ___ | ___ |
| Snapshot | 4 | ___ | ___ |
| Entry Set | 3 | ___ | ___ |
| Hash Stability | 3 | ___ | ___ |
| API Clean State | 3 | ___ | ___ |
| Resistance Test | 9 | ___ | ___ |
| Audit | 4 | ___ | ___ |

**TOTAL:** ___ / 44 checks passed

---

## 🚦 GO/NO-GO DECISION

**Overall Status:** ___ (PASS / FAIL)

**If ANY critical check failed, document which:**
- ___
- ___
- ___

**Recommendation:**
- [ ] Proceed to Scenario 7 (ALL checks passed)
- [ ] STOP, fix issues, restart Phase A (ANY check failed)

---

## 📝 NOTES / ANOMALIES

**Anything even slightly odd:**
- Delays:
- Unexpected logs:
- UI behavior:
- Other observations:

---

**Submit this completed template for audit review.**
