# Payment System Analysis - SitePilot ERP

**Document Version:** 1.0  
**Analysis Date:** April 2026

---

## Table of Contents

1. Overview
2. Payment Against Purchase Order (PO)
3. Payment Against Purchase Invoice
4. Payment Allocation
5. Payment Request System
6. Against Invoice Payments
7. Advance Payments
8. Edge Cases & Scenarios
9. Database Design
10. API & Controller Flow
11. UI/UX Flow Suggestions
12. Best Practices

---

## 1. Overview

### 1.1 Payment Lifecycle in ERP

```
PO Created → GRN Created → Invoice Received → Payment Made → Ledger Updated
```

### 1.2 Key Entities

| Entity | Table |
|--------|-------|
| Purchase Order | purchase_orders |
| GRN | grns |
| Purchase Invoice | purchase_invoices |
| Payment | payments_module |
| Vendor | suppliers |
| Ledger | supplier_transactions |

---

## 2. Payment Against PO

### 2.1 When and Why Against PO

| Scenario | Use Case |
|----------|---------|
| Milestone Payment | Project-based payment before goods |
| Emergency Procurement | Urgent materials needed |
| Contractual Advance | Security deposit |
| Partial Delivery | Payment on partial PO |

### 2.2 Workflow

```
PO Created → Select "Advance" Type → Link to PO ID → Update PO Balance
```

### 2.3 Validations

```php
$validator = [
    'supplier_id' => 'required|exists:suppliers,id',
    'purchase_order_id' => 'required|exists:purchase_orders,id',
    'payment_type' => 'required|in:advance',
    'amount' => 'required|numeric|min:0.01',
];
```

---

## 3. Payment Against Invoice

### 3.1 Standard Workflow

```
PO → GRN → Invoice Created → Payment Against Invoice → Payment Status Updated
```

### 3.2 Partial and Full Payments

```
Full:   Invoice ₹10,000 → Payment ₹10,000 → Status: "paid"
Partial: Invoice ₹10,000 → Payment ₹5,000  → Status: "partially paid"
```

### 3.3 Accounting Entries

```
Dr. Accounts Payable    ₹10,000
Cr. Bank/Cash                    ₹10,000
```

---

## 4. Payment Allocation

### 4.1 Single Payment → Multiple Invoices

```
Payment: ₹15,000
Invoice 1: ₹8,000
Invoice 2: ₹7,000
Total: ₹15,000 (allocated)
```

### 4.2 Unallocated Payments

```
Payment: ₹15,000
Allocated: ₹10,000
Unallocated: ₹5,000 → Becomes advance
```

### 4.3 Validation Rules

```
- Total allocation must equal payment amount (±tolerance)
- Individual allocation ≤ invoice balance
- No duplicate invoice allocation
```

---

## 5. Payment Request System

### 5.1 Workflow

```
Draft → Requested → Pending Approval → Approved/Rejected → Paid
```

### 5.2 Roles

| Role | Task |
|------|------|
| Requester | Create request |
| Manager | Approve/Reject |
| Finance | Process payment |

---

## 6. Against Invoice Payments

### 6.1 Database Fields

```
payment_type: 'against_invoice'
purchase_invoice_id: (direct link)
allocations: (multi-invoice via allocation table)
```

### 6.2 Validation

```php
'payment_type' => 'in:against_invoice,mixed',
'allocations' => 'required|array|min:1',
'allocations.*.invoice_id' => 'required|exists:purchase_invoices,id',
'allocations.*.amount' => 'required|numeric|min:0.01',
```

---

## 7. Advance Payments

### 7.1 What Are Advances

Payment made without invoice, linked to PO for future adjustment.

### 7.2 Adjustment Flow

```
Advance Paid (₹10,000) → Invoice Received (₹10,000) → Adjust Advance → Invoice Paid
```

### 7.3 Balance Tracking

```
Total Advance: ₹30,000
Utilized:      ₹13,000
Balance:       ₹17,000
```

---

## 8. Edge Cases

### 8.1 Overpayment
Reject or convert excess to advance.

### 8.2 Underpayment
Allow, update invoice to "partially paid".

### 8.3 Refunds
Create negative payment linked to original.

---

## 9. Database Design

### 9.1 Current Tables

| Table | Purpose |
|-------|---------|
| payments_module | Main payment records |
| payment_module_allocations | Payment-invoice linking |

### 9.2 Recommended Additions

```sql
payment_requests (approval workflow)
payment_request_items (request details)
advance_adjustments (advance utilization)
payment_audit_logs (audit trail)
```

### 9.3 Relationships

```
Suppliers → 1:N → Payments
        → 1:N → Invoices
        
Invoices → 1:N → Allocations → N:1 → Payments
```

---

## 10. API & Controller Flow

### 10.1 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/payments | List payments |
| POST | /api/payments | Create payment |
| GET | /api/payments/{id} | Get payment |
| PUT | /api/payments/{id} | Update payment |
| DELETE | /api/payments/{id} | Delete payment |
| GET | /api/payments/advances | Get advances |
| GET | /api/payments/outstanding | Unpaid invoices |

### 10.2 Controller Responsibilities

- PaymentsModuleController: CRUD operations
- PaymentService: Business logic
- LedgerHelper: Accounting entries

---

## 11. UI/UX Flow

### 11.1 Create Payment Screen

```
- Payment Number (auto-generated)
- Payment Date
- Supplier (select)
- Site (select)
- Payment Type (radio: Against Invoice/Advance/Mixed)
- Amount
- Payment Mode
- Reference Number
- Notes
- Payment Proof (file upload)
- Invoice Allocations (table for Against Invoice type)
```

### 11.2 Invoice Selection UI

- Checkbox to select invoices
- Input field for allocation amount per invoice
- Auto-calculate unallocated balance

### 11.3 Advance Tracking UI

- List all advances with balance
- Button to adjust to invoice
- Modal for selection

---

## 12. Best Practices

### 12.1 Validation Rules

| Field | Rule |
|-------|------|
| supplier_id | required, exists |
| payment_date | required, date, before_or_equal:today |
| amount | required, numeric, min:0.01 |
| payment_type | required, in:types |
| allocations | required_if:type, against_invoice |

### 12.2 Audit Logs

```php
PaymentAuditLog::create([
    'payment_id' => $payment->id,
    'action' => 'created|updated|deleted',
    'changed_by' => auth()->id(),
]);
```

### 12.3 Security

- Permission: manage-payment
- Amount limits by role
- Required approval for large amounts
- Audit trail for all changes

### 12.4 Financial Controls

- Daily reconciliation
- Tolerance: ₹0.01
- Period locking
- Balance verification

---

## Payment Type Decision

| Question | Answer | Type |
|----------|--------|------|
| Invoice exists? | Yes | against_invoice |
| Invoice exists? | No, PO exists | advance |
| Invoice exists? | No PO | advance (on account) |
| Both invoice + advance | Yes | mixed |

---

**End of Document**
