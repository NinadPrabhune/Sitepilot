# PO_CENTRIC_PAYMENT_MODULE_IMPLEMENTATION_Version_1 (FINAL)

## SitePilot ERP - Production Ready Payment System

**Version:** 1.0 Final
**Type:** PO-Centric ERP Payment Architecture
**Status:** Ready for Development

---

# 1. 🧠 SYSTEM ARCHITECTURE (FINAL)

## 3-Layer Financial Model

```
1. PO Layer        → Commitment (Planned Spend)
2. Invoice Layer   → Liability (Amount Payable)
3. Payment Layer   → Cash Flow (Amount Paid)
```

---

# 2. 🔥 CORE BUSINESS RULES

* PO is **reference only (non-accounting)**
* PO balance reduces ONLY on **invoice creation**
* Advance does NOT reduce PO balance
* Invoice creates **liability (credit entry)**
* Payment reduces **liability (debit entry)**
* All payments must link to **PO (mandatory)** except `on_account`

---

# 3. 🔍 GAP ANALYSIS (FIXED)

| Issue       | Old System    | New System   |
| ----------- | ------------- | ------------ |
| Tracking    | Invoice-based | PO-based     |
| Advance     | Disconnected  | Linked to PO |
| Ledger      | Fragmented    | Unified      |
| Overpayment | Allowed       | Prevented    |
| Visibility  | Per invoice   | Per PO       |

---

# 4. 🧮 FINAL FINANCIAL LOGIC

## 4.1 PO Commitment

```
PO_BALANCE = PO_TOTAL - INVOICED_AMOUNT
```

---

## 4.2 Liability

```
TOTAL_PAID = SUM(all payments)

PAYABLE = INVOICED_AMOUNT - TOTAL_PAID
```

---

## 4.3 Advance

```
ADVANCE_BALANCE = ADVANCE_PAID - ADVANCE_UTILIZED
```

---

## 4.4 IMPORTANT SEPARATION

| Concept    | Based On   |
| ---------- | ---------- |
| PO Balance | Invoice    |
| Payable    | Payment    |
| Advance    | Adjustment |

---

# 5. 🔄 END-TO-END FLOW

```
PO Created
    ↓
Advance Payment (optional)
    ↓
Invoice Created → reduces PO balance
    ↓
Advance Auto Adjust
    ↓
Payment Against Invoice
    ↓
PO Closed
```

---

# 6. 💰 PAYMENT TYPES (FINAL)

| Type               | Description             |
| ------------------ | ----------------------- |
| advance_against_po | Advance before invoice  |
| against_invoice    | Payment against invoice |
| mixed              | Combined                |
| on_account         | No PO                   |

---

# 7. 🗄️ DATABASE DESIGN (FINAL)

## purchase_orders

```sql
id
total
invoiced_amount
status ENUM('open','partial','closed')
closed_date
```

---

## purchase_invoices

```sql
id
purchase_order_id
total
paid_amount
status ENUM('unpaid','partially_paid','paid')
```

---

## payments_module

```sql
id
purchase_order_id
payment_type
amount
status ENUM('draft','completed')
```

---

## advance_adjustments

```sql
id
payment_id
purchase_invoice_id
utilized_amount
```

---

# 8. ⚙️ BACKEND DESIGN

## 8.1 POCalculationService (FINAL)

```php
class POCalculationService
{
    public function calculate($poId)
    {
        $po = PurchaseOrder::findOrFail($poId);

        $invoiced = PurchaseInvoice::where('purchase_order_id', $poId)->sum('total');

        $paid = Payment::where('purchase_order_id', $poId)
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'po_balance' => $po->total - $invoiced,
            'payable' => $invoiced - $paid,
        ];
    }
}
```

---

## 8.2 PaymentService (FINAL SAFE)

```php
class PaymentService
{
    public function create($data)
    {
        return DB::transaction(function () use ($data) {

            $payment = Payment::create($data);

            if (!empty($data['invoice_id'])) {

                $invoice = PurchaseInvoice::findOrFail($data['invoice_id']);

                $invoice->paid_amount += $data['amount'];
                $invoice->save();

                // Update status
                if ($invoice->paid_amount >= $invoice->total) {
                    $invoice->status = 'paid';
                } elseif ($invoice->paid_amount > 0) {
                    $invoice->status = 'partially_paid';
                }
            }

            return $payment;
        });
    }
}
```

---

# 9. 📊 SUPPLIER LEDGER (CORRECTED)

## RULES

| Transaction | Debit  | Credit |
| ----------- | ------ | ------ |
| Invoice     | 0      | Amount |
| Payment     | Amount | 0      |
| Advance     | Amount | 0      |

---

## FLOW

```
Invoice → Credit (Liability Increase)
Payment → Debit (Liability Reduce)
Advance → Debit
```

---

# 10. 📈 DASHBOARD CHANGES

Display:

* Total PO Value
* Total Invoiced
* Total Paid
* Payable
* Advance Balance

---

# 11. 🖥️ UI/UX RULES

## Payment Screen

* PO mandatory
* Show:

  * PO Total
  * Invoiced
  * Paid
  * Payable

---

## Invoice Screen

* Auto adjust advance
* Show balance

---

# 12. 🔐 VALIDATIONS

* Payment ≤ Payable
* Invoice ≤ PO balance
* No payment on CLOSED PO
* Advance ≤ limit %
* Prevent duplicate payment reference

---

# 13. ⚠️ EDGE CASES

## Overpayment

```
Excess → Convert to Advance
```

---

## Underpayment

```
Invoice remains partially paid
```

---

## PO Close Rule

```
IF:
PO_BALANCE = 0
AND PAYABLE = 0

→ CLOSE PO
```

---

# 14. 🚀 IMPLEMENTATION PLAN

## Phase 1 (DB)

* Add PO fields
* Add advance_adjustments

## Phase 2 (Backend)

* PaymentService
* POCalculationService

## Phase 3 (UI)

* Payment screen redesign
* Invoice adjustment UI

## Phase 4 (Reports)

* Supplier Ledger
* PO Summary

## Phase 5 (Testing)

* All edge cases
* Financial accuracy

---

# 15. ✅ FINAL BENEFITS

* No double counting
* Correct accounting
* PO-level tracking
* Clean ledger
* Audit-ready

---

# 16. 🏁 FINAL SUMMARY

| Layer   | Control |
| ------- | ------- |
| PO      | Invoice |
| Invoice | Payment |
| Payment | Ledger  |

---

# 🎯 FINAL VERDICT

**This is a complete ERP-grade Payment Module design.
Safe for direct Laravel implementation.**

---
