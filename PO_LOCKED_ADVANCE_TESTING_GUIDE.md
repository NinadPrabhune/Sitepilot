# PO-Locked Advance System - Testing Guide

## Pre-Testing Checklist
- [ ] Run migrations: `php artisan migrate`
- [ ] Verify feature flag is OFF: Check `.env` for `PO_LOCKED_ADVANCE_ENABLED=false`
- [ ] Verify shadow mode is OFF: Check `.env` for `SHADOW_MODE_ENABLED=false`
- [ ] Verify financial period locking is OFF: Check `.env` for `FINANCIAL_PERIOD_LOCKING_ENABLED=false`

## Test Scenarios

### 1. PO Allocation Test
**Objective**: Verify advance allocation works correctly for PO-based invoices when feature flag is enabled.

**Steps**:
1. Create a Purchase Order (PO)
2. Create a Supplier Advance linked to the PO
3. Approve and mark the advance as paid
4. Create a Purchase Invoice linked to the PO
5. Enable feature flag: Set `PO_LOCKED_ADVANCE_ENABLED=true` in `.env`
6. Trigger advance allocation
7. Verify allocation succeeds and creates advance_utilizations records with status='applied'
8. Verify supplier_advances.utilized_amount is updated correctly
9. Verify transaction_flow_id is generated and matches between advance and invoice

**Expected Result**: Advance is allocated to invoice with proper transaction flow ID and utilization records.

---

### 2. Cross-PO Block Test
**Objective**: Verify that advances from one PO cannot be allocated to invoices from another PO.

**Steps**:
1. Create PO-1 and PO-2 for the same supplier
2. Create Supplier Advance linked to PO-1
3. Create Purchase Invoice linked to PO-2
4. Enable feature flag
5. Attempt to allocate advance from PO-1 to invoice from PO-2

**Expected Result**: Allocation should fail with error message about PO mismatch or transaction flow mismatch.

---

### 3. Direct GRN Block Test
**Objective**: Verify that Direct GRN invoices (no PO) cannot use advance allocation.

**Steps**:
1. Create a Direct GRN invoice (po_id = null)
2. Enable feature flag
3. Attempt to create payment request for the invoice
4. Attempt to allocate advance

**Expected Result**:
- Payment request creation should show "Direct GRN Invoice" badge and warning
- Advance allocation should be blocked with error: "Direct GRN invoices cannot use advance allocation"
- Payment amount should be forced to full invoice amount

---

### 4. Partial Allocation Test
**Objective**: Verify that partial advance allocation works correctly.

**Steps**:
1. Create PO with advance amount of ₹50,000
2. Create invoice with grand_total of ₹100,000
3. Enable feature flag
4. Trigger advance allocation
5. Verify only ₹50,000 is allocated
6. Verify remaining ₹50,000 is available for payment

**Expected Result**: Advance allocated up to available amount, remaining balance calculated correctly.

---

### 5. Concurrent Allocation Test
**Objective**: Verify that concurrent allocation attempts are handled correctly with DB locking.

**Steps**:
1. Create PO with advance amount of ₹100,000
2. Create two invoices (Invoice-A, Invoice-B) each with grand_total of ₹75,000
3. Enable feature flag
4. Simultaneously trigger allocation for both invoices (use separate browser tabs or API calls)
5. Verify that one succeeds and the other fails or handles correctly

**Expected Result**: DB locking should prevent race conditions. One allocation succeeds, the other either fails or waits.

---

### 6. Financial Period Locking Test (Optional)
**Objective**: Verify that financial period locking prevents modifications to closed periods.

**Steps**:
1. Enable financial period locking: `FINANCIAL_PERIOD_LOCKING_ENABLED=true`
2. Create a financial period for a specific month/year
3. Close the period
4. Attempt to create or modify an invoice with date in the closed period

**Expected Result**: Operation should fail with error: "Financial period is closed."

---

### 7. Feature Flag OFF Test
**Objective**: Verify that when feature flag is OFF, the legacy system still works.

**Steps**:
1. Ensure `PO_LOCKED_ADVANCE_ENABLED=false`
2. Create PO, advance, and invoice
3. Attempt allocation using legacy system

**Expected Result**: Legacy allocation should work (if still available). New system should be bypassed with log warning.

---

### 8. Transaction Flow ID Test
**Objective**: Verify transaction flow IDs are generated correctly.

**Steps**:
1. Create PO with ID 100
2. Enable feature flag
3. Create Supplier Advance linked to PO
4. Create Purchase Invoice linked to PO
5. Verify transaction_flow_id format: `FLOW-PO-100`

**Expected Result**: Both advance and invoice should have matching transaction_flow_id.

---

### 9. Direct GRN Transaction Flow ID Test
**Objective**: Verify Direct GRN invoices get unique transaction flow IDs.

**Steps**:
1. Create Direct GRN invoice (no PO)
2. Enable feature flag
3. Verify transaction_flow_id format: `FLOW-DGRN-{uuid}`

**Expected Result**: Direct GRN invoice gets unique UUID-based flow ID.

---

### 10. Idempotency Test
**Objective**: Verify idempotency key prevents duplicate payment requests.

**Steps**:
1. Create invoice
2. Create payment request with idempotency_key = "test-key-123"
3. Attempt to create another payment request with same idempotency_key
4. Verify second request returns existing request

**Expected Result**: Second request should return existing payment request without creating duplicate.

---

### 11. Reservation Crash Recovery Test
**Objective**: Verify system handles crashes during reservation lifecycle without leaving orphaned reservations.

**Steps**:
1. Create PO with advance
2. Create invoice
3. Enable feature flag
4. Trigger reservation (reserveForPaymentRequest)
5. Simulate crash: Kill request mid-flow OR throw exception before applyReservation
6. Restart system
7. Run cleanup job (check for expired reservations)
8. Verify no stuck reserved entries in advance_utilizations table

**Expected Result**:
- No stuck reserved entries remain
- Orphans are auto-released or marked expired
- System can proceed with new allocations

---

### 12. Ledger Integrity Drift Test
**Objective**: Verify ledger double-entry accounting always balances (debits = credits).

**Steps**:
1. Run multiple allocations and reversals
2. Execute ledger balance check query:
   ```sql
   SELECT SUM(debit) - SUM(credit) FROM ledger_entries;
   ```
3. Verify result is 0
4. Run partial allocations
5. Run full reversals
6. Re-check ledger balance

**Expected Result**: Ledger balance must always be 0. Any non-zero result indicates system corruption.

---

### 13. Retry Duplication Test (API Retry Storm)
**Objective**: Verify system handles network retries without creating duplicate records.

**Steps**:
1. Create invoice
2. Simulate network retry storm: Send same allocation request 5-10 times simultaneously
3. Monitor advance_utilizations table
4. Monitor ledger_entries table
5. Monitor payment_requests table

**Expected Result**:
- Only 1 utilization record created
- Only 1 ledger entry created
- Only 1 payment request processed
- System should handle idempotency gracefully

---

### 14. Negative Allocation Prevention Test
**Objective**: Verify system blocks attempts to allocate more than available advance balance.

**Steps**:
1. Create advance with amount = ₹10,000
2. Create invoice with grand_total = ₹100,000
3. Attempt to force allocation of ₹15,000 (via API tampering or direct DB manipulation)
4. Verify system response

**Expected Result**:
- HARD BLOCK on allocation
- No partial override allowed
- Error message: "Allocation exceeds available advance balance"
- No records created in advance_utilizations

---

### 15. Time Drift / Backdated Entry Test
**Objective**: Verify system handles backdated entries correctly with period locking.

**Steps**:
1. Create financial period for Jan 2025 and close it
2. Enable financial period locking: `FINANCIAL_PERIOD_LOCKING_ENABLED=true`
3. Attempt to create invoice dated in Jan 2025
4. Attempt allocation on backdated invoice
5. Verify system behavior

**Expected Result**:
- Allocation blocked if period locked with error: "Financial period is closed"
- OR system recalculates safely if period not locked
- No corruption to closed period data

---

### 16. Multi-User Conflict Test (Real ERP Scenario)
**Objective**: Verify DB locking prevents concurrent conflicting operations.

**Steps**:
1. Create PO with advance
2. Create invoice
3. Enable feature flag
4. User A: Trigger advance allocation (simultaneous)
5. User B: Trigger advance reversal on same advance (simultaneous)
6. Monitor database locks
7. Verify final state

**Expected Result**:
- Locking prevents double state mutation
- One operation succeeds, other fails or waits
- No data corruption
- Clear error message for failed operation

---

### 17. Audit Trail Completeness Test
**Objective**: Verify every financial action creates complete audit trail.

**Steps**:
1. Perform operations: allocation, reservation, apply, reverse
2. Check advance_audit_logs table for each action
3. Verify each log entry has:
   - action type (created, allocated, reversed, locked, unlocked, approved, paid)
   - transaction_flow_id
   - old_value and new_value
   - amount
   - user_id
   - workspace_id, site_id
4. Check for missing audit entries
5. Check for orphan flow IDs

**Expected Result**:
- All actions logged
- No missing audit entries
- No orphan flow IDs
- Complete traceability for every financial operation

---

### 18. System Restart Consistency Test
**Objective**: Verify system state remains consistent after server restart.

**Steps**:
1. Perform operations in sequence:
   - Create advance allocation
   - Create reservation
   - Process partial payment
2. Restart server (php artisan serve restart)
3. Verify system state:
   - advance_utilizations table status
   - supplier_advances.utilized_amount
   - payment_requests status
   - ledger_entries balance
4. Attempt to continue operations

**Expected Result**:
- No state corruption
- All pending operations consistent
- Ledger balance remains 0
- System can continue normal operations

---

### 19. Partial Failure in Ledger Write Test (ACID Compliance)
**Objective**: Verify atomic transaction rollback when ledger write fails mid-transaction.

**Steps**:
1. Create advance and invoice setup
2. Modify LedgerDoubleEntryService to simulate failure after debit entry but before credit entry
3. Trigger advance allocation
4. Verify database state:
   - advance_utilizations table (should be empty if rollback succeeded)
   - ledger_entries table (should have no half-written entries)
   - supplier_advances.utilized_amount (should be unchanged)
5. Restore normal service code

**Expected Result**:
- Full atomic rollback (no partial state)
- No half-written ledger entries
- No orphaned utilization records
- System returns to pre-transaction state

---

### 20. Currency / Rounding Drift Test
**Objective**: Verify fractional rounding accumulation doesn't cause drift in totals.

**Steps**:
1. Create advance with amount = ₹99.99
2. Create invoice with grand_total = ₹99.99
3. Perform allocation
4. Create second invoice with grand_total = ₹99.99
5. Perform partial allocation from same advance (₹33.33)
6. Create third invoice with grand_total = ₹99.99
7. Perform remaining allocation (₹66.66)
8. Verify final reconciliation:
   - Total allocated = ₹99.99 (exact match)
   - No rounding drift in advance_utilizations
   - No rounding drift in ledger_entries
9. Cross-check: SUM(utilized_amount) should equal advance.amount

**Expected Result**:
- No drift in totals
- Final reconciliation = exact invoice total
- No fractional rounding errors accumulate
- All decimal precision maintained at 2 places

---

### 21. High Volume Batch Allocation Test
**Objective**: Verify system handles large batch allocations without timeout or memory issues.

**Steps**:
1. Create single PO with advance amount = ₹10,00,000
2. Create 500+ invoices linked to the PO (each with grand_total = ₹1,000 to ₹2,000)
3. Enable feature flag
4. Trigger batch allocation for all 500+ invoices (via script or queue job)
5. Monitor:
   - Execution time (should complete within reasonable time)
   - Memory usage (should not spike excessively)
   - Database locks (should not cause deadlocks)
   - Batch processing behavior (should respect 50-record limit per batch)
6. Verify all allocations succeed
7. Verify ledger balance = 0
8. Verify utilized_amount = advance.amount

**Expected Result**:
- No timeout
- No memory spike
- Proper batching behavior (50 records per batch)
- All allocations succeed
- System remains stable under load

---

## Post-Testing Checklist
- [ ] All test scenarios documented with results
- [ ] Feature flag tested in both ON and OFF states
- [ ] Database verified for correct data after each test
- [ ] Logs reviewed for any errors or warnings
- [ ] Any failures documented with root cause analysis

## Rollback Procedure (if issues found)
1. Disable feature flag: `PO_LOCKED_ADVANCE_ENABLED=false`
2. Run rollback migrations if needed
3. Restore database from backup
4. Document issues for investigation
