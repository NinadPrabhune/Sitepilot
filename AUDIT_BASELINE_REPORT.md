# Payment Module Audit Baseline Report

**Date:** April 10, 2026  
**Database:** sitepilot_live  
**Purpose:** Establish baseline for Payment Module Redesign (Phase 1)

---

## Instructions

This report should be populated by running the SQL queries in `database/audit_queries/` against the current database before any Phase 2 changes are applied.

**To populate this report:**
1. Run each SQL query in `database/audit_queries/`
2. Record the results in the corresponding section below
3. Verify totals match expectations
4. Save this report as the baseline reference for Phase 3 validation

---

## 1. PO-Based Payments

**Query:** `database/audit_queries/01_po_based_payments.sql`

### Against PO Payments
- **Count:** [FILL IN]
- **Total Amount:** [FILL IN]

### Advance Against PO Payments
- **Count:** [FILL IN]
- **Total Amount:** [FILL IN]

### Total PO-Based Payments
- **Combined Count:** [FILL IN]
- **Combined Amount:** [FILL IN]

**Notes:** These payments will be migrated to invoice-based payments in Phase 3.

---

## 2. Payment Allocations

**Query:** `database/audit_queries/02_payment_allocations.sql`

### Allocation Records
- **Total Allocations:** [FILL IN]
- **Total Allocated Amount:** [FILL IN]

### Allocations by Payment Type
- **Against PO:** [FILL IN] allocations, [FILL IN] total
- **Advance Against PO:** [FILL IN] allocations, [FILL IN] total

**Notes:** The `payment_module_allocations` table will be dropped in Phase 3 after migrating PO-based payments.

---

## 3. Direct GRN Invoices

**Query:** `database/audit_queries/03_direct_grn_invoices.sql`

### Direct GRN Invoice Count
- **Total Direct GRN Invoices:** [FILL IN]
- **Total Direct GRN Amount:** [FILL IN]

### By Payment Status
- **Unpaid:** [FILL IN] count, [FILL IN] amount
- **Partially Paid:** [FILL IN] count, [FILL IN] amount
- **Paid:** [FILL IN] count, [FILL IN] amount

**Notes:** Direct GRN invoices bypass PO entirely. These are edge cases that will continue to work in the invoice-centric system.

---

## 4. Payment Requests Without Payments

**Query:** `database/audit_queries/04_payment_requests_without_payments.sql`

### Pending Payment Requests
- **Total Requests Without Payments:** [FILL IN]

### By Status
- **Pending:** [FILL IN] count, [FILL IN] requested, [FILL IN] approved
- **Approved:** [FILL IN] count, [FILL IN] requested, [FILL IN] approved
- **Partially Approved:** [FILL IN] count, [FILL IN] requested, [FILL IN] approved
- **Partially Paid:** [FILL IN] count, [FILL IN] requested, [FILL IN] approved
- **Rejected:** [FILL IN] count, [FILL IN] requested, [FILL IN] approved

**Notes:** These are active payment requests that need to be processed before Phase 3 migration or handled carefully during migration.

---

## 5. Supplier Ledger Balances

**Query:** `database/audit_queries/05_supplier_ledger_balances.sql`

### Overall Ledger Summary
- **Total Suppliers with Transactions:** [FILL IN]
- **Total Debits (Positive Balance):** [FILL IN]
- **Total Credits (Negative Balance):** [FILL IN]
- **Net Balance:** [FILL IN]

### Ledger Summary by Reference Type
- **PO:** [FILL IN] count, [FILL IN] debit, [FILL IN] credit, [FILL IN] reference
- **GRN:** [FILL IN] count, [FILL IN] debit, [FILL IN] credit, [FILL IN] reference
- **Invoice:** [FILL IN] count, [FILL IN] debit, [FILL IN] credit, [FILL IN] reference
- **Payment:** [FILL IN] count, [FILL IN] debit, [FILL IN] credit, [FILL IN] reference
- **Advance:** [FILL IN] count, [FILL IN] debit, [FILL IN] credit, [FILL IN] reference
- **Adjustment:** [FILL IN] count, [FILL IN] debit, [FILL IN] credit, [FILL IN] reference

**Notes:** This baseline will be used to verify ledger balance integrity after Phase 3 migration. The net balance should remain the same before and after migration.

---

## 6. Additional Baseline Metrics

### Purchase Orders
- **Total POs:** [FILL IN from manual query]
- **POs with Invoices:** [FILL IN from manual query]
- **POs without Invoices:** [FILL IN from manual query]
- **Total PO Amount:** [FILL IN from manual query]

### Purchase Invoices
- **Total Invoices:** [FILL IN from manual query]
- **Total Invoice Amount:** [FILL IN from manual query]
- **Invoices with PO:** [FILL IN from manual query]
- **Invoices without PO:** [FILL IN from manual query]

### Payments
- **Total Payments:** [FILL IN from manual query]
- **Total Payment Amount:** [FILL IN from manual query]
- **Against Invoice Payments:** [FILL IN from manual query]
- **Against PO Payments:** [FILL IN from manual query]
- **Advance Payments:** [FILL IN from manual query]

---

## 7. Risk Assessment

Based on the baseline data:

### High Risk Areas
- [ ] PO-based payments with large amounts (>₹100,000)
- [ ] Payment allocations that don't sum to payment total
- [ ] Direct GRN invoices with large amounts
- [ ] Payment requests with large approved amounts
- [ ] Supplier ledger balances that don't reconcile

### Medium Risk Areas
- [ ] Payment requests in approved/partially_approved status
- [ ] Invoices without PO references
- [ ] POs without any invoices

### Low Risk Areas
- [ ] Small value transactions (<₹10,000)
- [ ] Recently created records (last 30 days)

---

## 8. Validation Checklist

Before proceeding to Phase 2:

- [ ] All SQL queries executed successfully
- [ ] All baseline values recorded
- [ ] Totals verified for reasonableness
- [ ] No data anomalies detected
- [ ] Risk assessment completed
- [ ] Backup of current database state taken

---

## 9. Sign-Off

**Baseline Completed By:** [NAME]  
**Date:** [DATE]  
**Verified By:** [NAME]  
**Date:** [DATE]

**Approved for Phase 2:** [ ] YES / [ ] NO  
**Comments:** [COMMENTS]
