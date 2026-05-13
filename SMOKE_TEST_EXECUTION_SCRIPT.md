# Smoke Test Execution Script - PO-Locked Advance System

**Purpose**: Validate system behaves exactly like before upgrade (feature flag OFF)
**Risk Level**: LOW (Safe Mode - feature flag OFF)

---

## Test 1: Basic System Flow Test
**Objective**: Verify core ERP functionality works normally

### Steps:
1. Login to application at http://127.0.0.1:8000
2. Navigate to Suppliers
3. Create a new supplier (if not exists)
4. Navigate to Purchase Orders
5. Create a new PO
6. Navigate to Purchase Invoices
7. Create a new invoice linked to the PO

### Expected Behavior:
- All screens load without errors
- No warnings in UI
- Forms submit successfully
- No financial engine activation

### Validation Queries:
```sql
-- Verify invoice created
SELECT * FROM purchase_invoices ORDER BY id DESC LIMIT 1;

-- Verify no transaction_flow_id (feature flag OFF)
SELECT id, invoice_number, po_id, transaction_flow_id, grn_type 
FROM purchase_invoices 
ORDER BY id DESC LIMIT 1;

-- Expected: transaction_flow_id = NULL, grn_type = NULL (feature flag OFF)
```

### Log Verification:
```bash
# Check Laravel logs for errors
tail -n 50 storage/logs/laravel.log

# Expected: No errors, no PO-Locked engine logs
```

---

## Test 2: Invoice Creation Test (Without Advance)
**Objective**: Verify invoice creation works normally without advance allocation

### Steps:
1. Navigate to Purchase Invoices
2. Click "Create Invoice"
3. Fill invoice details (without linking to advance)
4. Save invoice

### Expected Behavior:
- Invoice saves successfully
- No advance allocation triggered
- No reservation created
- No ledger entries created

### Validation Queries:
```sql
-- Verify invoice created
SELECT id, invoice_number, grand_total, po_id, transaction_flow_id 
FROM purchase_invoices 
ORDER BY id DESC LIMIT 1;

-- Verify no advance utilization records
SELECT COUNT(*) as utilization_count 
FROM advance_utilizations 
WHERE purchase_invoice_id = (SELECT id FROM purchase_invoices ORDER BY id DESC LIMIT 1);

-- Expected: utilization_count = 0

-- Verify no ledger entries (table may not exist yet)
SELECT COUNT(*) as ledger_count 
FROM ledger_entries;

-- Expected: ledger_count = 0 or table doesn't exist
```

### Log Verification:
```bash
# Check finance logs
tail -n 50 storage/logs/finance.log

# Expected: No entries or only informational logs
# Should NOT see: "Advance allocated", "Reservation created", "Ledger entry"
```

---

## Test 3: Payment Request Test
**Objective**: Verify payment request works normally without triggering new system

### Steps:
1. Navigate to Purchase Invoices
2. Select an invoice
3. Click "Create Payment Request"
4. Fill payment details
5. Submit payment request

### Expected Behavior:
- Payment request created successfully
- No reservation created
- No allocation triggered
- UI shows legacy behavior

### Validation Queries:
```sql
-- Verify payment request created
SELECT id, purchase_invoice_id, requested_amount, status, idempotency_key 
FROM payment_requests 
ORDER BY id DESC LIMIT 1;

-- Verify no advance utilization records
SELECT COUNT(*) as utilization_count 
FROM advance_utilizations 
WHERE purchase_invoice_id IN (
    SELECT purchase_invoice_id FROM payment_requests ORDER BY id DESC LIMIT 1
);

-- Expected: utilization_count = 0

-- Verify advance_utilizations table status
SELECT status, COUNT(*) 
FROM advance_utilizations 
GROUP BY status;

-- Expected: Empty or only legacy records (no 'reserved', 'applied', 'reversed')
```

### Log Verification:
```bash
# Check finance logs for reservation activity
tail -n 100 storage/logs/finance.log | grep -i "reservation\|allocation\|ledger"

# Expected: No output (grep finds nothing)
```

---

## Test 4: UI Modal Test
**Objective**: Verify payment modal displays correctly with feature flag OFF

### Steps:
1. Navigate to Purchase Invoices
2. Click "Create Payment Request" on an invoice
3. Observe the modal display

### Expected Behavior:
- Modal loads without errors
- Advance used/remaining may show 0 or legacy values
- No "PO-Based Invoice" or "Direct GRN Invoice" badges (feature flag OFF)
- No advance allocation warnings (feature flag OFF)

### Validation:
- Check modal displays correctly
- No JavaScript errors in browser console
- No network errors in browser console

---

## Test 5: Database Integrity Check
**Objective**: Verify database state is consistent

### Validation Queries:
```sql
-- Check purchase_invoices table
SELECT COUNT(*) as invoice_count FROM purchase_invoices;

-- Check payment_requests table
SELECT COUNT(*) as payment_request_count FROM payment_requests;

-- Check advance_utilizations table (should be empty or legacy only)
SELECT COUNT(*) as utilization_count FROM advance_utilizations;

-- Check financial_periods table (should be empty)
SELECT COUNT(*) as period_count FROM financial_periods;

-- Check advance_audit_logs table (should be empty)
SELECT COUNT(*) as audit_count FROM advance_audit_logs;

-- Expected: utilization_count, period_count, audit_count = 0 or legacy data only
```

---

## Test 6: Configuration Verification
**Objective**: Verify feature flags remain OFF

### Validation Commands:
```bash
# Check feature flags in config
php artisan config:show finance.po_locked_advance_enabled
# Expected: false

php artisan config:show finance.shadow_mode_enabled
# Expected: false (or not found)

php artisan config:show finance.financial_period_locking_enabled
# Expected: false
```

---

## Smoke Test Completion Checklist

- [ ] Supplier creation works
- [ ] PO creation works
- [ ] Invoice creation works (without advance)
- [ ] Payment request creation works
- [ ] No reservation records created
- [ ] No allocation triggered
- [ ] No ledger entries created
- [ ] No errors in Laravel logs
- [ ] No PO-Locked engine logs in finance logs
- [ ] Feature flags remain OFF
- [ ] Database integrity verified

---

## Expected Result Summary

**SUCCESS CRITERIA:**
- All basic ERP operations work normally
- No behavioral change from pre-upgrade state
- No new system activation (feature flag OFF)
- No errors in logs
- Database integrity maintained

**FAILURE INDICATORS:**
- Reservation records created (should not happen with flag OFF)
- Allocation triggered (should not happen with flag OFF)
- Ledger entries created (should not happen with flag OFF)
- PO-Locked engine logs in finance.log
- Feature flag changed to ON
- Database integrity issues

---

## Next Steps After Smoke Test

If smoke test passes:
- System is validated for production deployment with feature flag OFF
- Proceed to production deployment following deployment guide
- Keep feature flag OFF until shadow mode validation

If smoke test fails:
- Document failure details
- Check logs for errors
- Verify feature flag status
- Fix issues before proceeding
