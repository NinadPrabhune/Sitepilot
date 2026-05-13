# PO_CENTRIC_PAYMENT_MODULE_SIMPLIFIED_V2

## SitePilot ERP - Final Simplified Payment Design

**Version:** 2.0
**Design:** Ultra Simplified PO-Based Payment System
**Status:** Ready for Direct Implementation

---

# 1. 🎯 FINAL PAYMENT TYPES

Only **2 types allowed**:

```php
const PAYMENT_TYPE_ADVANCE_AGAINST_PO = 'advance_against_po';
const PAYMENT_TYPE_AGAINST_PO = 'against_po';
```

---

# 2. 🔥 CORE RULES

* Payment is ALWAYS linked to **Purchase Order (PO)**
* No invoice selection in UI
* No allocation table
* No invoice-based payment
* Invoice settlement is **automatic (FIFO)**
* Payment status is always **completed**
* Payment amount must NOT exceed PO payable

---

# 3. 🧮 FINANCIAL LOGIC

## PO Balance

```
PO_BALANCE = PO_TOTAL - INVOICED_AMOUNT
```

---

## Payable

```
PAYABLE = INVOICED_AMOUNT - TOTAL_PAID
```

---

## Advance

```
ADVANCE = TOTAL_ADVANCE_PAID - USED
```

---

# 4. 🔄 SYSTEM FLOW

```
Select PO
   ↓
Enter Payment Amount
   ↓
Validate Amount ≤ Payable
   ↓
Save Payment (status = completed)
   ↓
Auto FIFO Allocation to Invoices
   ↓
Update Invoice Status
```

---

# 5. ⚙️ AUTO INVOICE SETTLEMENT (FIFO)

### Logic:

* Oldest invoice first
* Fully settle before next
* Partial if remaining amount

---

### Example

```
PO = ₹10,000

Invoices:
Inv1 = 5,000
Inv2 = 5,000

Payment = ₹6,000
```

Result:

```
Inv1 → Paid (5,000)
Inv2 → Partial (1,000)
```

---

# 6. 🗄️ DATABASE (FINAL)

## payments_module

| Field             | Description                     |
| ----------------- | ------------------------------- |
| id                | PK                              |
| payment_number    | Unique                          |
| supplier_id       | Supplier                        |
| purchase_order_id | PO                              |
| amount            | Amount                          |
| payment_type      | advance_against_po / against_po |
| status            | ALWAYS = completed              |
| payment_date      | Date                            |
| reference_number  | Ref                             |
| notes             | Notes                           |

---

# 7. 🔐 VALIDATIONS (CRITICAL)

## 7.1 Payment Limit

```
IF amount > PAYABLE
→ ERROR: Payment exceeds PO payable
```

---

## 7.2 PO Status

```
IF PO = CLOSED
→ BLOCK PAYMENT
```

---

## 7.3 Mandatory Fields

* PO required
* Supplier required
* Amount > 0

---

# 8. 📊 LEDGER / A/C STATEMENT (IMPORTANT UI ADD)

## Show in Payment Screen:

### Supplier Ledger

| Date | Type | Debit | Credit | Balance |
| ---- | ---- | ----- | ------ | ------- |

---

## Rules:

| Transaction | Effect |
| ----------- | ------ |
| Invoice     | Credit |
| Payment     | Debit  |

---

## Live Display:

When selecting PO:

👉 Show:

* Total PO Value
* Total Invoiced
* Total Paid
* Payable
* Last 10 Ledger Entries

---

# 9. 🖥️ BLADE UI REDESIGN

## URL:

```
/payments-module/create
```

---

## REMOVE (IMPORTANT)

❌ Invoice selection
❌ Allocation UI
❌ Mixed payment
❌ Against invoice
❌ Advance adjustment UI

---

## NEW UI STRUCTURE

### Section 1: PO Selection

* Dropdown: Purchase Order
* Auto load:

  * Supplier
  * PO Total
  * Invoiced
  * Paid
  * Payable

---

### Section 2: Payment Entry

* Payment Type (2 options only)
* Amount input
* Payment Mode
* Reference Number
* Notes

---

### Section 3: Live Summary Card

```
PO Total: ₹
Invoiced: ₹
Paid: ₹
Payable: ₹
After Payment: ₹
```

---

### Section 4: Supplier Ledger (NEW)

* Table view
* Scrollable
* Latest transactions

---

### Section 5: Submit

* Save Payment

---

# 10. ⚙️ CONTROLLER LOGIC

```php
validate amount <= payable

create payment (status = completed)

autoAllocateToInvoices(payment)
```

---

# 11. 📦 INVOICE STATUS UPDATE

```php
if paid >= total → paid
if paid > 0 → partially_paid
else → unpaid
```

---

# 12. 🚀 BENEFITS

* Ultra simple system
* No complex allocation
* Fast UI
* Clean PO tracking
* Easy to maintain

---

# 13. ⚠️ LIMITATIONS

* No invoice-level payment tracking
* No manual allocation
* Not suitable for complex finance audit

---

# 14. 🏁 FINAL SUMMARY

| Feature              | Status    |
| -------------------- | --------- |
| PO-Based             | ✅         |
| FIFO Auto Settlement | ✅         |
| Allocation Table     | ❌ Removed |
| UI Simplified        | ✅         |
| Ledger Integrated    | ✅         |

---

# 🎯 FINAL VERDICT

**This is a clean, fast, PO-centric payment system
perfect for SitePilot ERP implementation.**
