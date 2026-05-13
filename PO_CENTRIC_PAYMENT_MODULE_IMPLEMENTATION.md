# PO-Centric Payment Module Implementation Plan
## SitePilot ERP - Purchase Order Based Payment Tracking

**Document Version:** 2.0  
**Analysis Date:** April 2026  
**Status:** Implementation Blueprint

---

## 1. 🔍 Current System Gap Analysis

### 1.1 Problems with Invoice-Based Payment Tracking

| Problem | Impact | Current Behavior |
|---------|--------|-----------------|
| No PO-level balance visibility | Cannot track total PO commitment vs payments | Payments link directly to invoices only |
| Advance disconnected | Unclear utilization against future invoices | Advance payments tracked separately |
| Supplier ledger fragmented | Hard to reconcile supplier account | Each invoice treated independently |
| No advance adjustment logic | Manual tracking required | No auto-adjustment when invoice received |
| Multiple invoices per PO | Cannot track cumulative position | Individual invoice settlement only |
| Overpayment undetected | PO overpayment not flagged | System allows unlimited payments |

### 1.2 Why PO-Based Tracking Is Required

1. **Complete Financial Position:** A single view shows total commitment, payments made, and balance due per PO
2. **Advance Utilization:** Automatically adjust advance when invoices are linked
3. **Audit Trail:** Every rupee traceable to PO from source to settlement
4. **Supplier Clarity:** Running PO balance visible at all times
5. **Control:** Prevent overpayment and ensure correct payment sequencing

### 1.3 Issues in Current Supplier Ledger

```
CURRENT STATE (Invoice-Centric):
┌─────────────────────────────────────────────────────────┐
│ Date     | Invoice No | Amount | Paid  | Balance        │
├─────────────────────────────────────────────────────────┤
│ Jan 01  | INV-001    | 10,000 | 5,000│ 5,000 (per invoice)│
│ Jan 15  | INV-002    | 8,000  | 8,000│ 0              │
│ Feb 10  | ADV-001    | 3,000  | 3,000│ (unlinked)     │
└─────────────────────────────────────────────────────────┘
PROBLEM: No PO context, advances disconnected, supplier position unclear
```

---

## 2. 🧠 New Payment Architecture (PO-Centric)

### 2.1 Core Definitions

| Term | Definition | Formula |
|------|------------|---------|
| **PO Total** | Original PO value | Direct from PO |
| **Advance Paid** | Total advances against PO | SUM(payments WHERE type = advance) |
| **Invoice Booked** | Invoices linked to PO | SUM(invoices.total) |
| **PO Balance** | Remaining amount payable | PO Total - Advance Paid - Invoice Booked |
| **Advance Utilized** | Advance adjusted to invoices | SUM(adjustments) |

### 2.2 PO Balance Calculation Formula

```
PO_STATUS_FIELDS (Computed):
┌──────────────────────────────────────────────────────────────────┐
│ advance_paid      = COALESCE(SUM(payments.amount), 0)        │
│                   WHERE payment_type IN ('advance_against_po')   │
│                   AND purchase_order_id = po.id             │
├──────────────────────────────────────────────────────────────────┤
│ invoiced_amount   = COALESCE(SUM(invoices.total), 0)         │
│                   WHERE purchase_order_id = po.id            │
├──────────────────────────────────────────────────────────────────┤
│ balance_amount   = po.total - advance_paid - invoiced_amount│
├──────────────────────────────────────────────────────────────────┤
│ adjustment      = SUM(advance_adjustments.utilized)        │
│                   WHERE purchase_order_id = po.id          │
└──────────────────────────────────────────────────────────────────┘
```

### 2.3 Payment Flow Diagram

```
                    ┌─────────────────┐
                    │   PO CREATED    │
                    │   ₹10,000       │
                    └────────┬────────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
              ▼                             ▼
    ┌──────────────────┐          ┌──────────────────┐
    │  ADVANCE PAYMENT │          │ INVOICE BOOKING  │
    │    ₹1,000       │          │    ₹5,000        │
    └────────┬────────┘          └────────┬────────┘
             │                            │
             │  ┌───────────────────────┘
             │  │
             ▼  ▼
    ┌────────────────────────────────────────┐
    │    ADJUSTMENT LOGIC (Auto-Adjust)       │
    │  - Check available advance            │
    │  - Apply advance to invoice          │
    │  - Calculate remaining payable      │
    └────────┬───────────────────────────┘
             │
             ▼
    ┌────────────────────────────────────────┐
    │     PO BALANCE UPDATE                 │
    │  PO Total:     ₹10,000                │
    │  - Advance:    ₹1,000                 │
    │  - Invoice:    ₹5,000                 │
    │  = Balance:   ₹4,000                 │
    └────────┬───────────────────────────┘
             │
             ▼
    ┌────────────────────────────────────────┐
    │     FINAL SETTLEMENT / PO CLOSURE     │
    │  - Remaining ₹4,000 paid              │
    │  - PO marked CLOSED                   │
    └────────────────────────────────────────┘
```

---

## 3. 💰 Payment Types Redesign

### 3.1 Payment Type Matrix

| Type | Code | PO Required | Invoice Required | Balance Check | Adjustment |
|------|------|------------|-----------------|--------------|-------------|
| **Advance Against PO** | `advance_against_po` | ✅ MANDATORY | ❌ | ✅ (advance %) | Manual |
| **Against PO Invoice** | `against_po_invoice` | ✅ MANDATORY | Optional | ✅ PO Balance | Auto-adjust advance |
| **Mixed Payment** | `mixed` | ✅ MANDATORY | Optional | ✅ PO Balance | Both |
| **On Account** | `on_account` | ❌ | ❌ | ❌ Supplier only | N/A |

### 3.2 Payment Type Decision Logic

```
FUNCTION getPaymentType($request):
    IF $request->purchase_order_id AND !$request->purchase_invoice_id:
        RETURN 'advance_against_po'          // No invoice yet
    
    IF $request->purchase_order_id AND $request->purchase_invoice_id:
        RETURN 'against_po_invoice'           // Invoice settlement
    
    IF $request->purchase_order_id AND $request->has_advance_component:
        RETURN 'mixed'                       // Both advance + invoice
    
    IF !$request->purchase_order_id:
        RETURN 'on_account'                  // No PO linked
    
    DEFAULT: RETURN 'on_account'
```

### 3.3 Advance Percentage Limits

```php
VALIDATION: advance_percentage
┌────────────────────────────┐
│ PO advance_max %: 25%      │  // Configurable per PO
│ Payment advance: ₹2,500     │  // 25% of ₹10,000
│ exceeds limit → REJECT      │
└────────────────────────────┘
```

---

## 4. 🔄 End-to-End Workflow (Detailed)

### 4.1 Case 1: Advance Payment Only

```
SCENARIO: PO Created → Advance Paid → No Invoice Yet
┌─────────────────────────────────────────────────────────────────┐
│ STEP 1: PO Create                                          │
│   - PO Number: PO-2026-001                                 │
│   - Supplier: ABC Corp                                     │
│   - Total Value: ₹10,000                                   │
│   - Status: OPEN                                          │
│   - advance_paid: 0                                        │
│   - balance_amount: ₹10,000                                │
├─────────────────────────────────────────────────────────────────┤
│ STEP 2: Advance Payment                                    │
│   - Payment Type: advance_against_po                       │
│   - Amount: ₹1,000 (10%)                                    │
│   - Linked PO: PO-2026-001                                 │
│   - Payment Date: Jan 5, 2026                              │
├─────────────────────────────────────────────────────────────────┤
│ STEP 3: PO Balance Update                                  │
│   - advance_paid: ₹1,000                                   │
│   - invoiced_amount: 0                                     │
│   - balance_amount: ₹9,000                                │
│   - Status: PARTIAL_ADVANCE                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Case 2: Invoice Booking With Advance Adjustment

```
SCENARIO: Invoice Received → Auto-Adjust Advance → Remaining Payable
┌─────────────────────────────────────────────────────────────────┐
│ CURRENT STATE: PO-2026-001                                  │
│   - Total: ₹10,000                                          │
│   - advance_paid: ₹1,000                                   │
│   - Invoiced: 0                                              │
│   - Balance: ₹9,000                                         │
├─��─��─────────────────────────────────────────────────────────────┤
│ STEP 1: Invoice Booking                                     │
│   - Invoice: INV-001                                        │
│   - Amount: ₹5,000                                         │
│   - Linked PO: PO-2026-001                                 │
│   - Status: PENDING_PAYMENT                                 │
├─────────────────────────────────────────────────────────────────┤
│ STEP 2: Advance Adjustment (AUTO)                           │
│   - Available advance: ₹1,000                               │
│   - Invoice amount: ₹5,000                                  │
│   - Advanced adjusted: ₹1,000                                │
│   - Remaining payable: ₹4,000                              │
├─────────────────────────────────────────────────────────────────┤
│ STEP 3: PO Balance Update                                   │
│   - advance_paid: ₹1,000                                    │
│   - invoiced_amount: ₹5,000                                 │
│   - advance_utilized: ₹1,000                                │
│   - balance_amount: ₹4,000                                 │
│   - Status: PARTLIALLY_PAID                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 4.3 Case 3: Final Settlement → PO Closure

```
SCENARIO: All Invoices Paid → PO Balance 0 → Close PO
┌─────────────────────────────────────────���───────────────────────┐
│ CURRENT STATE: PO-2026-001                                  │
│   - Total: ₹10,000                                          │
│   - advance_paid: ₹1,000                                   │
│   - invoiced_amount: ₹9,000                                  │
│   - Balance: ₹0                                            │
├─────────────────────────────────────────────────────────────────┤
│ STEP 1: Final Invoice                                         │
│   - Invoice: INV-002                                        │
│   - Amount: ₹4,000                                         │
│   - Advance available: ₹0 (fully utilized)                 │
│   - Remaining payable: ₹4,000                              │
├─────────────────────────────────────────────────────────────────┤
│ STEP 2: Final Payment                                       │
│   - Payment Type: against_po_invoice                        │
│   - Amount: ₹4,000                                          │
│   - Linked PO: PO-2026-001                                 │
│   - Status: PAID                                            │
├─────────────────────────────────────────────────────────────────┤
│ STEP 3: PO Closure                                           │
│   - balance_amount: ₹0                                      │
│   - Status: CLOSED                                          │
│   - closed_date: [current date]                              │
│   - all_invoices_paid: TRUE                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. 🧮 PO Balance Calculation Logic (VERY IMPORTANT)

### 5.1 Primary Formula

```
BALANCE_AMOUNT = PO_TOTAL 
              - ADVANCE_PAID 
              - INVOICED_AMOUNT 
              + ADVANCE_UTILIZED 
              + ADJUSTMENTS
```

### 5.2 Detailed Calculation Scenarios

#### Scenario A: Simple - No Advance

| Field | Value |
|-------|-------|
| PO Total | ₹10,000 |
| Advance Paid | ₹0 |
| Invoiced Amount | ₹5,000 |
| Advance Utilized | ₹0 |
| **Balance** | **₹5,000** |

#### Scenario B: With Advance - Partially Utilized

| Field | Value |
|-------|-------|
| PO Total | ₹10,000 |
| Advance Paid | ₹2,500 |
| Invoiced Amount | ₹5,000 |
| Advance Utilized | ₹2,500 |
| **Balance** | **₹2,500** (₹10,000 - ₹2,500 - ₹5,000 + ₹2,500) |

#### Scenario C: Advance Exceeds Invoice

| Field | Value |
|-------|-------|
| PO Total | ₹10,000 |
| Advance Paid | ₹3,000 |
| Invoiced Amount | ₹2,000 |
| Advance Utilized | ₹2,000 |
| Remaining Advance (credit) | ₹1,000 |
| **Balance** | **₹5,000** |

#### Scenario D: Multiple Invoices Cumulative

| Invoice | Amount | Advance Utilized | Paid | Balance |
|---------|--------|------------------|------|---------|
| INV-001 | ₹3,000 | ₹1,000 | ₹2,000 | ₹0 |
| INV-002 | ₹4,000 | ₹1,000 | ₹3,000 | ₹0 |
| INV-003 | ₹3,000 | ₹0 | ₹3,000 | ₹0 |
| **TOTAL** | **₹10,000** | **₹2,000** | **₹8,000** | **₹0** |

### 5.3 PO Balance Service Implementation

```php
class POCalculationService
{
    public function calculatePOBalance(int $poId): array
    {
        $po = PurchaseOrder::findOrFail($poId);
        
        $advancePaid = Payment::where('purchase_order_id', $poId)
            ->where('payment_type', 'advance_against_po')
            ->where('status', 'completed')
            ->sum('amount');
        
        $invoicedAmount = PurchaseInvoice::where('purchase_order_id', $poId)
            ->sum('total');
        
        $advanceUtilized = AdvanceAdjustment::where('purchase_order_id', $poId)
            ->sum('utilized_amount');
        
        $adjustments = $this->getPOAdjustments($poId);
        
        $balance = $po->total 
            - $advancePaid 
            - $invoicedAmount 
            + $advanceUtilized 
            + $adjustments;
        
        return [
            'po_id' => $poId,
            'po_total' => $po->total,
            'advance_paid' => $advancePaid,
            'invoiced_amount' => $invoicedAmount,
            'advance_utilized' => $advanceUtilized,
            'adjustments' => $adjustments,
            'balance_amount' => $balance,
            'status' => $this->determinePOStatus($balance),
        ];
    }
    
    public function getAvailableAdvance(int $poId): float
    {
        $advancePaid = $this->getAdvancePaid($poId);
        $utilized = $this->getAdvanceUtilized($poId);
        
        return $advancePaid - $utilized;
    }
}
```

---

## 6. 🗄️ Database Redesign

### 6.1 Modified: purchase_orders table

```sql
ALTER TABLE purchase_orders ADD COLUMN:
- advance_paid DECIMAL(15,2) DEFAULT 0
- invoiced_amount DECIMAL(15,2) DEFAULT 0
- balance_amount DECIMAL(15,2) DEFAULT 0
- total_paid DECIMAL(15,2) DEFAULT 0
- status ENUM('open','partial_advance','partially_paid','fully_paid','closed','cancelled')
- closed_date TIMESTAMP NULL
- updated_at TIMESTAMP

RENAME existing columns if needed for clarity.
```

### 6.2 Modified: payments_module table

```sql
ALTER TABLE payments_module ADD COLUMN:
- purchase_order_id BIGINT UNSIGNED NULL
  FOREIGN KEY REFERENCES purchase_orders(id)
- adjustment_type ENUM('advance','invoice','mixed','none')
- advance_utilized DECIMAL(15,2) DEFAULT 0
- payment_sequence INT DEFAULT 0
- is_reversed BOOLEAN DEFAULT FALSE
- reversed_by BIGINT UNSIGNED NULL

MODIFY existing columns:
- payment_type ENUM('advance_against_po','against_po_invoice','mixed','on_account')
```

### 6.3 New Table: advance_adjustments

```sql
CREATE TABLE advance_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    purchase_invoice_id BIGINT UNSIGNED,
    advance_amount DECIMAL(15,2) NOT NULL,
    utilized_amount DECIMAL(15,2) NOT NULL,
    balance_amount DECIMAL(15,2) NOT NULL,
    adjustment_date DATE NOT NULL,
    notes TEXT,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (payment_id) REFERENCES payments_module(id),
    FOREIGN KEY (purchase_invoice_id) REFERENCES purchase_invoices(id)
);
```

### 6.4 New Table: po_payment_summary

```sql
CREATE TABLE po_payment_summary (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL UNIQUE,
    po_total DECIMAL(15,2) NOT NULL,
    advance_paid DECIMAL(15,2) DEFAULT 0,
    advance_utilized DECIMAL(15,2) DEFAULT 0,
    advance_balance DECIMAL(15,2) DEFAULT 0,
    invoiced_amount DECIMAL(15,2) DEFAULT 0,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    balance_amount DECIMAL(15,2) NOT NULL,
    invoice_count INT DEFAULT 0,
    payment_count INT DEFAULT 0,
    status ENUM('open','partial_advance','partially_paid','fully_paid','closed') DEFAULT 'open',
    last_payment_date DATE,
    closed_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
);
```

### 6.5 Modified: supplier_transactions (Ledger)

```sql
ALTER TABLE supplier_transactions ADD COLUMN:
- purchase_order_id BIGINT UNSIGNED NULL
  FOREIGN KEY REFERENCES purchase_orders(id)
- reference_type ENUM('payment','invoice','adjustment','advance','refund')
- reference_number VARCHAR(100)
- running_balance DECIMAL(15,2)

MODIFY:
- Add INDEX on (supplier_id, purchase_order_id, transaction_date)
```

### 6.6 Entity Relationships

```
ER DIAGRAM:
┌─────────────────┐       ┌──────────────────────────┐       ┌─────────────────┐
│ purchase_orders │──1:N──│ po_payment_summary       │◄──1:1──│ purchase_orders │
└────────┬────────┘       └──────────────────────────┘       └─────────────────┘
         │
         │ 1:N
         ▼
┌─────────────────┐       ┌──────────────────────────┐
│ payments_module │◄─────│ advance_adjustments     │
│                 │       │                          │
│ - advance_paid  │       │ - advance_amount        │
│ - balance       │       │ - utilized_amount        │
└────────┬────────┘       │ - balance_amount        │
         │                └──────────────────────────┘
         │ 1:N
         ▼
┌─────────────────┐
│ purchase_invoices│
└─────────────────┘

SUPPLEMENTARY:
┌─────────────────┐       ┌──────────────────────────┐
│ supplier        │──1:N──│ supplier_transactions    │
│                 │       │                          │
│                 │       │ - purchase_order_id (FK) │
└─────────────────┘       │ - reference_type         │
                          └──────────────────────────┘
```

---

## 7. ⚙️ Laravel Backend Design

### 7.1 Controllers

#### PaymentController

```php
class PaymentController extends Controller
{
    protected PaymentService $paymentService;
    
    public function store(PaymentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. Validate PO link requirement
            $this->validatePOLink($request);
            
            // 2. Calculate available balance
            $balance = $this->poService->calculatePOBalance(
                $request->purchase_order_id
            );
            
            // 3. Validate payment against balance
            $this->validatePaymentAmount($request, $balance);
            
            // 4. Create payment
            $payment = $this->paymentService->createPayment($request);
            
            // 5. Update PO balance
            $this->poService->updatePOBalance($request->purchase_order_id);
            
            // 6. Create ledger entry
            $this->ledgerService->createEntry($payment);
            
            return $payment;
        });
    }
    
    protected function validatePOLink($request): void
    {
        $requiresPO = in_array($request->payment_type, [
            'advance_against_po',
            'against_po_invoice',
            'mixed'
        ]);
        
        if ($requiresPO && !$request->purchase_order_id) {
            throw new ValidationException(
                'PO is required for payment type: ' . $request->payment_type
            );
        }
    }
    
    protected function validatePaymentAmount($request, $balance): void
    {
        if ($request->payment_type !== 'advance_against_po') {
            if ($request->amount > $balance['balance_amount']) {
                throw new ValidationException(
                    "Payment amount ₹{$request->amount} exceeds PO balance ₹{$balance['balance_amount']}"
                );
            }
        }
        
        // Advance percentage check
        if ($request->payment_type === 'advance_against_po') {
            $maxAdvance = $balance['po_total'] * (config('po.max_advance_percentage') / 100);
            if ($request->amount > $maxAdvance) {
                throw new ValidationException(
                    "Advance cannot exceed " . config('po.max_advance_percentage') . "% of PO value"
                );
            }
        }
    }
}
```

#### PurchaseOrderController (Balance Updates)

```php
class PurchaseOrderController extends Controller
{
    protected POService $poService;
    
    public function updateBalance(Request $request, int $poId)
    {
        return DB::transaction(function () use ($poId, $request) {
            // Recalculate all payment-related fields
            $summary = $this->poService->recalculatePOSummary($poId);
            
            // Update PO record
            $po = PurchaseOrder::findOrFail($poId);
            $po->advance_paid = $summary['advance_paid'];
            $po->invoiced_amount = $summary['invoiced_amount'];
            $po->balance_amount = $summary['balance_amount'];
            $po->total_paid = $summary['total_paid'];
            $po->status = $summary['status'];
            $po->save();
            
            // Upsert summary table
            POPaymentSummary::updateOrCreate(
                ['purchase_order_id' => $poId],
                $summary
            );
            
            return $po;
        });
    }
}
```

### 7.2 Service Layer

#### PaymentService

```php
class PaymentService
{
    protected POCalculationService $poCalculation;
    protected LedgerService $ledger;
    
    public function createPayment(PaymentRequest $request): Payment
    {
        $payment = DB::transaction(function () use ($request) {
            // Determine payment sequence
            $sequence = $this->getNextPaymentSequence(
                $request->purchase_order_id
            );
            
            $payment = Payment::create([
                'payment_number' => $this->generatePaymentNumber(),
                'purchase_order_id' => $request->purchase_order_id,
                'payment_type' => $request->payment_type,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'payment_mode' => $request->payment_mode,
                'reference_number' => $request->reference_number,
                'supplier_id' => $request->supplier_id,
                'payment_sequence' => $sequence,
                'status' => 'completed',
                'created_by' => auth()->id(),
            ]);
            
            // Handle advance or invoice allocation
            if ($request->payment_type === 'against_po_invoice') {
                $this->allocateToInvoice($payment, $request);
            }
            
            return $payment;
        });
        
        return $payment;
    }
    
    protected function allocateToInvoice(Payment $payment, PaymentRequest $request): void
    {
        $availableAdvance = $this->poCalculation->getAvailableAdvance(
            $payment->purchase_order_id
        );
        
        $invoiceAmount = $request->purchase_invoice_id 
            ? PurchaseInvoice::findOrFail($request->purchase_invoice_id)->total 
            : 0;
        
        $utilizedAdvance = min($availableAdvance, $invoiceAmount);
        
        if ($utilizedAdvance > 0) {
            AdvanceAdjustment::create([
                'purchase_order_id' => $payment->purchase_order_id,
                'payment_id' => $payment->id,
                'purchase_invoice_id' => $request->purchase_invoice_id,
                'advance_amount' => $availableAdvance,
                'utilized_amount' => $utilizedAdvance,
                'balance_amount' => $availableAdvance - $utilizedAdvance,
                'adjustment_date' => now()->toDateString(),
                'created_by' => auth()->id(),
            ]);
        }
    }
}
```

#### POCalculationService

```php
class POCalculationService
{
    public function recalculatePOSummary(int $poId): array
    {
        $po = PurchaseOrder::findOrFail($poId);
        
        $advancePaid = Payment::where('purchase_order_id', $poId)
            ->whereIn('payment_type', ['advance_against_po', 'mixed'])
            ->where('status', 'completed')
            ->sum('amount');
        
        $invoices = PurchaseInvoice::where('purchase_order_id', $poId)
            ->get();
        
        $invoicedAmount = $invoices->sum('total');
        
        $advanceUtilized = AdvanceAdjustment::where('purchase_order_id', $poId)
            ->sum('utilized_amount');
        
        $payments = Payment::where('purchase_order_id', $poId)
            ->whereIn('payment_type', ['against_po_invoice', 'mixed'])
            ->where('status', 'completed')
            ->get();
        
        $paidAmount = $payments->sum('amount') + $advancePaid;
        
        $balanceAmount = $po->total - $advancePaid - $invoicedAmount + $advanceUtilized;
        
        $status = $this->determineStatus($balanceAmount, $advancePaid, $invoicedAmount);
        
        return [
            'purchase_order_id' => $poId,
            'po_total' => $po->total,
            'advance_paid' => $advancePaid,
            'advance_utilized' => $advanceUtilized,
            'advance_balance' => $advancePaid - $advanceUtilized,
            'invoiced_amount' => $invoicedAmount,
            'paid_amount' => $paidAmount,
            'balance_amount' => max(0, $balanceAmount),
            'invoice_count' => $invoices->count(),
            'payment_count' => $payments->count() + ($advancePaid > 0 ? 1 : 0),
            'status' => $status,
            'last_payment_date' => $payments->max('payment_date'),
        ];
    }
    
    protected function determineStatus(
        float $balance,
        float $advance,
        float $invoiced
    ): string {
        if ($balance <= 0 && $invoiced > 0) {
            return 'closed';
        }
        if ($balance < 0) {
            return 'overpaid';
        }
        if ($advance > 0 && $invoiced === 0) {
            return 'partial_advance';
        }
        if ($invoiced > 0 && $balance > 0) {
            return 'partially_paid';
        }
        return 'open';
    }
}
```

### 7.3 Transaction Flow with DB::transaction()

```php
// Comprehensive Payment Flow
DB::transaction(function () use ($request, $paymentId) {
    // 1. Get/Create payment record
    $payment = Payment::findOrFail($paymentId);
    
    // 2. Update PO summary
    $poSummary = $this->pocalculation->recalculatePOSummary(
        $payment->purchase_order_id
    );
    
    $po = PurchaseOrder::findOrFail($payment->purchase_order_id);
    $po->update($poSummary);
    
    // 3. Create/update summary record
    POPaymentSummary::updateOrCreate(
        ['purchase_order_id' => $po->id],
        $poSummary
    );
    
    // 4. Create supplier ledger entry
    $this->ledger->createSupplierEntry([
        'supplier_id' => $po->supplier_id,
        'purchase_order_id' => $po->id,
        'transaction_type' => 'payment',
        'reference_id' => $payment->id,
        'reference_number' => $payment->payment_number,
        'debit' => $payment->amount,
        'credit' => 0,
        'transaction_date' => $payment->payment_date,
    ]);
    
    // 5. Create audit log
    PaymentAuditLog::create([
        'payment_id' => $payment->id,
        'action' => 'created',
        'previous_balance' => $this->getPreviousBalance($po->id),
        'new_balance' => $poSummary['balance_amount'],
        'changed_by' => auth()->id(),
    ]);
});
```

---

## 8. 📊 Supplier Ledger Report Redesign (VERY IMPORTANT)

### 8.1 Recommended Ledger Format

```
SUPPLIER LEDGER REPORT (PO-Centric)
Suppler: ABC Corp | Period: Jan-Feb 2026 | Currency: INR
══════════════════════════════════════════════════════════════════════════════════

| Date     | PO No     | Ref No   | Type          | Debit(₹) | Credit(₹) | Balance(₹) | Remarks          |
|----------|-----------|----------|---------------|----------|-----------|------------|-----------------|
| Jan 01   | PO-001    | PO-001   | PO Created    | 0        | 10,000    | 10,000     | PO Created      |
| Jan 05   | PO-001    | PAY-001  | Advance       | 1,000    | 0         | 9,000      | Advance Paid    |
| Jan 10   | PO-001    | INV-001  | Invoice       | 5,000    | 0         | 14,000     | Invoice Booked  |
| Jan 12   | PO-001    | ADJ-001  | Adjustment    | 1,000    | 0         | 13,000     | Advance Utilized|
| Jan 15   | PO-001    | PAY-002  | Invoice Pay   | 4,000    | 0         | 9,000      | Partial Payment |
| Feb 01   | PO-001    | INV-002  | Invoice       | 4,000    | 0         | 13,000     | Invoice Booked  |
| Feb 05   | PO-001    | PAY-003  | Final Pay     | 5,000    | 0         | 8,000      | Final Settlement|
| Feb 10   | PO-001    | CLS-001  | PO Closed     | 0        | 0         | 0          | PO Balance 0    |

══════════════════════════════════════════════════════════════════════════════════
SUMMARY: Total Debit: ₹20,000 | Total Credit: ₹10,000 | Balance: ₹0
══════════════════════════════════════════════════════════════════════════════════
```

### 8.2 PO-Specific Ledger Query

```php
function getPOLedger(int $poId): array
{
    $po = PurchaseOrder::findOrFail($poId);
    
    $entries = collect();
    
    // PO Creation Entry
    $entries->push([
        'date' => $po->created_at,
        'po_number' => $po->po_number,
        'reference' => $po->po_number,
        'type' => 'PO Created',
        'debit' => 0,
        'credit' => $po->total,
        'balance' => $po->total,
        'remarks' => 'Purchase Order Created',
    ]);
    
    // Advance Payments
    $advances = Payment::where('purchase_order_id', $poId)
        ->where('payment_type', 'advance_against_po')
        ->get();
    
    $runningBalance = $po->total;
    foreach ($advances as $advance) {
        $runningBalance -= $advance->amount;
        $entries->push([
            'date' => $advance->payment_date,
            'po_number' => $po->po_number,
            'reference' => $advance->payment_number,
            'type' => 'Advance',
            'debit' => $advance->amount,
            'credit' => 0,
            'balance' => $runningBalance,
            'remarks' => 'Advance Payment',
        ]);
    }
    
    // Invoices Booked
    $invoices = PurchaseInvoice::where('purchase_order_id', $poId)->get();
    foreach ($invoices as $invoice) {
        $runningBalance += $invoice->total;
        $entries->push([
            'date' => $invoice->invoice_date,
            'po_number' => $po->po_number,
            'reference' => $invoice->invoice_number,
            'type' => 'Invoice',
            'debit' => $invoice->total,
            'credit' => 0,
            'balance' => $runningBalance,
            'remarks' => 'Invoice Booked',
        ]);
    }
    
    // Adjustments
    $adjustments = AdvanceAdjustment::where('purchase_order_id', $poId)->get();
    foreach ($adjustments as $adj) {
        $runningBalance -= $adj->utilized_amount;
        $entries->push([
            'date' => $adj->adjustment_date,
            'po_number' => $po->po_number,
            'reference' => $adj->payment->payment_number ?? 'N/A',
            'type' => 'Adjustment',
            'debit' => $adj->utilized_amount,
            'credit' => 0,
            'balance' => $runningBalance,
            'remarks' => 'Advance Utilized to Invoice',
        ]);
    }
    
    // Invoice Payments
    $payments = Payment::where('purchase_order_id', $poId)
        ->where('payment_type', 'against_po_invoice')
        ->get();
    
    foreach ($payments as $payment) {
        $runningBalance -= $payment->amount;
        $entries->push([
            'date' => $payment->payment_date,
            'po_number' => $po->po_number,
            'reference' => $payment->payment_number,
            'type' => 'Invoice Payment',
            'debit' => $payment->amount,
            'credit' => 0,
            'balance' => $runningBalance,
            'remarks' => 'Invoice Settlement',
        ]);
    }
    
    return $entries->sortBy('date')->values()->all();
}
```

### 8.3 Summary Statistics

```
┌─────────────────────────────────────────────────────────────────┐
│                     PO PAYMENT SUMMARY                         │
├─────────────────────────────────────────────────────────────────┤
│ PO Number:          PO-2026-001       Supplier:      ABC Corp    │
│ PO Date:           Jan 1, 2026      Expected Date: Feb 28, 2026 │
├─────────────────────────────────────────────────────────────────┤
│ FINANCIAL POSITION:                                             │
│   PO Total:          ₹10,000                                     │
│   Advance Paid:    ₹1,000 ↓ (10% of PO)                        │
│   Invoiced:        ₹9,000                                       │
│   Paid Amount:    ₹5,000                                       │
│   ────────────────────────                                      │
│   Balance Due:     ₹4,000                                      │
├─────────────────────────────────────────────────────────────────┤
│ PAYMENT TIMELINE:                                               │
│   Jan 05:  Advance Paid        ₹1,000                            │
│   Jan 10:  Invoice #1          ₹5,000                            │
│   Jan 15:  Invoice Paid        ₹4,000  (Advance Adjust: ₹1,000)   │
│   Feb 10:  Final Payment      ₹4,000                            │
├─────────────────────────────────────────────────────────────────┤
│ STATUS: CLOSED                                                  │
└─���─���─────────────────────────────────────────────────────────────┘
```

---

## 9. 📈 Project Dashboard Impact

### 9.1 Required Dashboard Metrics

| Metric | Source | Display |
|--------|--------|---------|
| Total PO Value | SUM(purchase_orders.total) | ₹ |
| Paid Amount | SUM(advance_paid + payments) | ₹ |
| Invoiced Amount | SUM(invoiced_amount) | ₹ |
| Remaining Balance | SUM(balance_amount) | ₹ |
| Open POs | COUNT WHERE status = 'open') | Count |
| Pending Invoices | COUNT WHERE status = 'pending_payment') | Count |
| Advance Utilized | SUM(advance_adjustments.utilized) | ₹ |

### 9.2 Dashboard Widgets

```
DASHBOARD: Purchase Overview (PO-Centric)
┌─────────────────────────────────────────────────────────────────────────┐
│ ┌───────────────────┐ ┌───────────────────┐ ┌───────────────────┐ │
│ │   OPEN POs       │ │  TOTAL PO VALUE  │ │   ADVANCE PAID   │ │
│ │      25          │ │   ₹2,50,000      │ │    ₹45,000       │ │
│ └───────────────────┘ └───────────────────┘ └───────────────────┘ │
│ ┌───────────────────┐ ┌───────────────────┐ ┌───────────────────┐ │
│ │  INVOICED        │ │   PAID THIS MONTH │ │  PENDING BALANCE │ │
│ │   ₹1,85,000      │ │    ₹95,000       │ │    ₹65,000       │ │
│ └───────────────────┘ └───────────────────┘ └───────────────────┘ │
├─────────────────────────────────────────────────────────────────────────┤
│                  RECENT POs WITH PAYMENT STATUS                    │
│ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ PO No │ Supplier │ Total │ Paid │ Invoiced │ Balance │ Status   │ │
│ ├─────────────────────────────────────────────────────────────────┤ │
│ │ PO-001│ ABC Corp │ 10,000│1,000│ 9,000   │ 0       │ CLOSED   │ │
│ │ PO-002│ XYZ Ltd  │ 5,000 │0    │ 3,000   │ 2,000   │ PARTIAL  │ │
│ │ PO-003│ PQR Inc  │ 8,000 │1,600│ 0       │ 6,400   │ADVANCE  │ │
│ └─────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

### 9.3 Alert Definitions

```php
ALERT_THRESHOLDS = [
    'overpayment_warning' => 101,      // % of PO value
    'advance_exceeded' => 25,           // Configurable %
    'pending_invoices_days' => 30,    // Days threshold
    'unmatched_advance_days' => 45,    // Unadjusted advance
];

ALERTS_GENERATED:
┌───────────────────────────────────┐
│ ⚠️ ALERTS                          │
├──────────────────────────────────��┤
│ ⚠️ PO-003: Advance ₹1,600 unmatched│
│    for 45+ days                    │
│ ⚠️ PO-005: Payment exceeds        │
│    PO balance by ₹500              │
│ ⚠️ 3 pending invoices for PO-002   │
└───────────────────────────────────┘
```

---

## 10. 🖥️ UI/UX Redesign

### 10.1 Payment Creation Screen

```
SCREEN: Create Payment
═══════════════════════════════════════════════════════════════════════
[1] NEW PAYMENT                               [SAVE] [CANCEL]

┌─ MANDATORY FIELDS ────────────────────────────────────────────────┐
│ Payment Date:    [__________]  Payment Mode: [Cash ▼]           │
│ Supplier:        [ABC Corp       ]  Site:      [Site ▼]         │
├─ PAYMENT TYPE (Select One) ───────────────────────────────────────┤
│ ○ Advance Against PO    ○ Against PO Invoice    ○ Mixed      │
│ ○ On Account (No PO)                                        │
├─ PO SELECTION (Required for PO-Types) ─────────────────────────┤
│ PO Number:    [PO-2026-001 ▼]    [🔍 Lookup]                   │
│                                                                  │
│ ┌─ PO DETAILS ────────────────────────────────────────────────┐ │
│ │ PO Total:          ₹10,000    │ Supplier:  ABC Corp        │ │
│ │ Advance Paid:     ₹1,000     │ Status:    PARTIAL          │ │
│ │ Invoiced:          ₹5,000     │ Balance:   ₹4,000           │ │
│ └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│ Amount:  [__________]  Max: ₹4,000 (PO Balance)                 │
│                                                                  │
│ Reference: [_____________]  Notes: [________________________] │
└─────────────────────────────────────────────────────────────────┘
```

### 10.2 Invoice Booking Screen with Advance Auto-Adjust

```
SCREEN: Create Invoice
═══════════════════════════════════════════════════════════════════════
[1] NEW INVOICE                              [SAVE] [CANCEL]

┌─ MANDATORY FIELDS ────────────────────────────────────────────────┐
│ Invoice Number: [INV-001       ]  Invoice Date: [__________]     │
│ Supplier:       [ABC Corp       ]  PO: [PO-2026-001 ▼]          │
└─────────────────────────────────────────────────────────────────┘

┌─ LINE ITEMS ─────────────────────────────────────────────────────┐
│ Item    │ Description  │ Qty  │ Rate   │ Amount                  │
│ ────────┼──────────────┼──────┼───────���┼���────────────────────────│
│ 1       │ Material A   │ 10   │ 500    │ ₹5,000                  │
├──────────────────────────────────────────────────────────────────┤
│                                          TOTAL:      ₹5,000      │
└─────────────────────────────────────────────────────────────────┘

┌─ ADVANCE ADJUSTMENT PANEL ────────────────────────────────────────┐
│                                                                  │
│ Available Advance (from PO): ₹1,000                             │
│ ─────────────────────────────────────                            │
│ ✓ Auto-adjust advance to this invoice                           │
│   Adjusted Amount:        ₹1,000                                 │
│   Remaining Payable:     ₹4,000                                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 10.3 PO Detail Screen with Balance Breakdown

```
SCREEN: Purchase Order Detail - PO-2026-001
═══════════════════════════════════════════════════════════════════════

[EDIT PO] [MAKE PAYMENT] [ADD INVOICE] [VIEW LEDGER] [CLOSE PO]

┌─ ORDER SUMMARY ─────────────────────────────────────────────────┐
│ PO Number:    PO-2026-001    Supplier:    ABC Corp              │
│ PO Date:      Jan 1, 2026    Status:      CLOSED               │
│ Total Value: ₹10,000          Closed:     Feb 10, 2026        │
└─────────────────────────────────────────────────────────────────┘

┌─ FINANCIAL POSITION ────────────────────────────────────────────┐
│                                                                  │
│   PO TOTAL      ₹10,000  ╱╲                                     │
│   Advance       -₹1,000  ╲╱  ────► Balance: ₹4,000            │
│   Invoiced      -₹5,000        (PO AVAILABLE FOR PAYMENT)       │
│   ────────────────────────                                     │
│   BALANCE        ₹4,000                                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

┌─ PAYMENT HISTORY ───────────────────────────────────────────────┐
│ Date     │ Payment No │ Type      │ Amount │ Adjusted │ Balance │
│ ─────────────────────│──────────│────────│──────────│─────────│
│ Jan 05  │ PAY-001    │ Advance  │ 1,000  │ -        │ 9,000   │
│ Jan 15  │ PAY-002    │ Invoice  │ 4,000  │ 1,000*   │ 4,000   │
│ Feb 10  │ PAY-003    │ Final    │ 4,000  │ -        │ 0       │
│                                                  *Advance used│
└─────────────────────────────────────────────────────────────────┘

┌─ INVOICE HISTORY ───────────────────────────────────────────────┐
│ Date     │ Invoice No │ Amount  │ Status      │ Paid    │ Due    │
│ ─────────────────────│──────────│─────────────│────────│────────│
│ Jan 10  │ INV-001    │ 5,000   │ PAID        │ 4,000* │ 1,000  │
│ Feb 01  │ INV-002    │ 4,000   │ PAID        │ 4,000  │ 0      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 11. 🔐 Validation Rules

### 11.1 Core Validation Matrix

| Rule | Condition | Action |
|------|------------|--------|
| PO Required | payment_type IN (advance_against_po, against_po_invoice, mixed) | REJECT if NULL |
| Payment ≤ Balance | amount > po.balance_amount | REJECT with error |
| Advance ≤ Max % | amount > po.total × max_advance% | REJECT with error |
| No Duplicate Payment | payment_number exists | REJECT if duplicate |
| PO Not Closed | po.status = 'closed' | REJECT if closed |
| Invoice Belongs to PO | invoice.purchase_order_id != request.po_id | REJECT |
| Invoice Not Fully Paid | invoice.balance <= 0 | WARN or REJECT |

### 11.2 Validation Implementation

```php
class PaymentValidator
{
    public static function rules(string $paymentType): array
    {
        $baseRules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:cash,bank,upi',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
        
        $poRules = [
            'purchase_order_id' => 'required|exists:purchase_orders,id',
        ];
        
        $invoiceRules = [
            'purchase_invoice_id' => 'nullable|exists:purchase_invoices,id',
        ];
        
        switch ($paymentType) {
            case 'advance_against_po':
                return array_merge($baseRules, $poRules, [
                    'purchase_order_id' => 'required|exists:purchase_orders,id|po_open|advance_within_limit',
                ]);
                
            case 'against_po_invoice':
                return array_merge($baseRules, $poRules, $invoiceRules, [
                    'purchase_order_id' => 'required|exists:purchase_orders,id|po_not_closed',
                    'amount' => 'numeric|lte:po_balance',
                ]);
                
            case 'mixed':
                return array_merge($baseRules, $poRules, $invoiceRules);
                
            case 'on_account':
                return $baseRules;
                
            default:
                return $baseRules;
        }
    }
    
    public static function withMessages(): array
    {
        return [
            'purchase_order_id.required' => 'PO is required for this payment type.',
            'purchase_order_id.po_open' => 'This PO is closed. Create a new PO for additional payments.',
            'purchase_order_id.po_not_closed' => 'Cannot make payments against a closed PO.',
            'amount.lte' => 'Payment amount (:input) exceeds PO balance (:max).',
            'purchase_order_id.advance_within_limit' => 'Advance cannot exceed :max% of PO value.',
        ];
    }
}
```

### 11.3 Custom Validation Rules

```php
Validator::extend('po_open', function ($attribute, $value, $parameters) {
    $po = PurchaseOrder::find($value);
    return $po && in_array($po->status, ['open', 'partial_advance', 'partially_paid']);
});

Validator::extend('advance_within_limit', function ($attribute, $value, $parameters) {
    $po = PurchaseOrder::find(request('purchase_order_id'));
    $maxAdvance = config('po.max_advance_percentage', 25);
    $maxAmount = $po->total * ($maxAdvance / 100);
    
    $currentAdvance = Payment::where('purchase_order_id', $po->id)
        ->where('payment_type', 'advance_against_po')
        ->sum('amount');
    
    return ($currentAdvance + $value) <= $maxAmount;
});

Validator::extend('lte_po_balance', function ($attribute, $value, $parameters) {
    $po = PurchaseOrder::find(request('purchase_order_id'));
    return $value <= $po->balance_amount;
});
```

---

## 12. ⚠️ Edge Cases

### 12.1 Overpayment Handling

```
SCENARIO: Payment > PO Balance
┌───────────────────────────────────────────────────────────────────┐
│ PO Total:    ₹10,000                                  │
│ Paid:       ₹6,500  (Excess ₹500)                     │
│ Balance:    -₹500                                     │
├───────────────────────────────────────────────────────────────────┤
│ RULE: Convert excess to advance (on_account)           │
│ RESULT:                                            │
│   - Regular payment: ₹6,000                          │
│   - Excess to advance: ₹500                          │
│   - PO balance: ₹0 (closed)                       │
│   - Advance balance: ₹500 (available for future)    │
└───────────────────────────────────────────────────────────────────┘

IMPLEMENTATION:
```php
if ($paymentAmount > $poBalance) {
    $excess = $paymentAmount - $poBalance;
    $regularPayment = $poBalance;
    $excessAsAdvance = $excess;
    
    // Create main payment
    Payment::create([
        'amount' => $regularPayment,
        'payment_type' => 'against_po_invoice',
        // ...
    ]);
    
    // Create excess as advance
    Payment::create([
        'amount' => $excessAsAdvance,
        'payment_type' => 'advance_against_po',
        'notes' => 'Excess payment converted to advance',
        // ...
    ]);
}
```

### 12.2 Underpayment - Keep PO Open

```
SCENARIO: Partial Payment, Invoice Remains Unpaid
┌───────────────────────────────────────────────────────────────────┐
│ Invoice:  ₹5,000                                     │
│ Payment:  ₹3,000                                      │
│ Balance:  ₹2,000                                     │
├───────────────────────────────────────────────────────────────────┤
│ RESULT: Invoice status = PARTIALLY_PAID               │
│         PO remains OPEN                              │
│         Balance due tracked                          │
└────────────────────────────────────��─��────────────────────────────┘
```

### 12.3 Multiple Invoices - Cumulative Tracking

```
SCENARIO: Multiple Invoices Against Single PO
┌───────────────────────────────────────────────────────────────────┐
│                                                               │
│ PO Total: ₹10,000                                               │
│ ─────────────────────────────────────────────────────────────── │
│ INVOICE 1:   ₹3,000    ADVANCE: ₹1,000 → PAID: ₹2,000          │
│ INVOICE 2:   ₹4,000    ADVANCE: -     → PAID: ₹4,000          │
│ INVOICE 3:   ₹3,000    ADVANCE: -     → PAID: ₹3,000          │
│ ─────────────────────────────────────────────────────────────── │
│ TOTAL:     ₹10,000    UTILIZED:₹1,000 → PAID:₹9,000 (90%)     │
│ BALANCE:   ₹1,000                                              │
│                                                               │
└───────────────────────────────────────────────────────────────────┘
```

### 12.4 PO Cancellation Handling

```
SCENARIO: PO Cancellation with Active Payments
┌───────────────────────────────────────────────────────────────────┐
│ PO Total: ₹10,000  | Advance Paid: ₹1,000                          │
│ Invoice: None (not yet received)                                  │
├───────────────────────────────────────────────────────────────────┤
│ PROCESS:                                                      │
│   1. Reverse advance payment                                   │
│   2. Update status to CANCELLED                              │
│   3. Create adjustment entry                                │
│   4. Alert: Request refund from supplier                    │
└───────────────────────────────────────────────────────────────────┘
```

### 12.5 Advance Without Invoice - Aging

```
SCENARIO: Unmatched Advance After 45 Days
┌───────────────────────────────────────────────────────────────────┐
│ ALERT: Unadjusted advance for PO-003                           │
│ ─────────────────────────────────────────────────────────── │
│ Advance: ₹1,600                                             │
│ Age: 48 days                                                 │
│ Last Invoice: None                                          │
├───────────────────────────────────────────────────────────────────┤
│ ACTIONS:                                                     │
│   1. Generate dashboard alert                               │
│   2. Notify procurement team                               ���
���   3. Allow manual write-off (with approval)                  │
└───────────────────────────────────────────────────────────────────┘
```

### 12.6 Split Payment Across Multiple POs

```
SCENARIO: Single Payment Allocated to Multiple POs
┌───────────────────────────────────────────────────────────────────┐
│ Payment: ₹10,000                                             │
│ Allocation:                                                 │
│   - PO-001: ₹4,000                                         │
│   - PO-002: ₹3,000                                         │
│   - PO-003: ₹3,000                                         │
│ ─────────────────────────────────────────────────────────── │
│ Total: ₹10,000                                              │
└───────────────────────────────────────────────────────────────────┘
```

---

## 13. 🚀 Implementation Plan (Step-by-Step)

### Phase 1: Database Changes (Week 1-2)

| Task | Description | Priority |
|------|-------------|----------|
| 1.1 | Add columns to `purchase_orders` | HIGH |
| 1.2 | Modify `payments_module` table | HIGH |
| 1.3 | Add `advance_adjustments` table | HIGH |
| 1.4 | Create `po_payment_summary` table | HIGH |
| 1.5 | Update `supplier_transactions` with PO link | MEDIUM |
| 1.6 | Create indexes for query performance | MEDIUM |

### Phase 2: Backend Logic (Week 3-4)

| Task | Description | Priority |
|------|-------------|----------|
| 2.1 | Implement `POCalculationService` | HIGH |
| 2.2 | Update `PaymentService` | HIGH |
| 2.3 | Implement advance adjustment logic | HIGH |
| 2.4 | Add validation rules | HIGH |
| 2.5 | Create PO balance update methods | HIGH |
| 2.6 | Implement ledger entry logic | MEDIUM |

### Phase 3: UI Updates (Week 5-6)

| Task | Description | Priority |
|------|-------------|----------|
| 3.1 | Redesign payment creation form | HIGH |
| 3.2 | Add PO selection with balance display | HIGH |
| 3.3 | Update invoice form with advance adjust | HIGH |
| 3.4 | Create PO detail view | HIGH |
| 3.5 | Supplier ledger UI | MEDIUM |

### Phase 4: Reports Redesign (Week 7)

| Task | Description | Priority |
|------|-------------|----------|
| 4.1 | PO payment summary report | HIGH |
| 4.2 | Supplier ledger report | HIGH |
| 4.3 | Advance utilization report | HIGH |
| 4.4 | Aging report | MEDIUM |

### Phase 5: Testing (Week 8)

| Task | Description | Priority |
|------|-------------|----------|
| 5.1 | Unit tests for calculation logic | HIGH |
| 5.2 | Payment flow tests | HIGH |
| 5.3 | Edge case tests | HIGH |
| 5.4 | Integration tests | HIGH |
| 5.5 | UAT with sample data | HIGH |

### Implementation Checklist

```
☑ Phase 1:
  □ Run migration scripts
  □ Verify table relationships
  □ Seed test data

☑ Phase 2:  
  □ Implement POCalculationService
  □ Run unit tests
  □ Update API endpoints

☑ Phase 3:
  □ Deploy to staging
  □ UI/UX review
  □ Browser testing

☑ Phase 4:
  □ Report generation
  □ PDF export testing

☑ Phase 5:
  □ Full regression
  □ Performance testing
  □ Go-live
```

---

## 14. ✅ Final Outcome

### 14.1 Financial Accuracy Improvements

| Metric | Before | After |
|--------|--------|-------|
| Balance Calculation | Per Invoice | Per PO |
| Advance Tracking | Manual | Automated |
| Supplier Clarity | Fragmented | Unified |
| Overpayment Detection | None | Real-time |
| Audit Trail | Partial | Complete |

### 14.2 Traceability Achievements

```
EVERY RUPEE TRACEABLE AT PO LEVEL:
┌────────────────────────────────────────────────────────────────┐
│                                                                │
│  Advance (₹1,000)  ──┬──► PO Balance ──► Invoice Adjustment │
│                     │                                         │
│  Payment (₹4,000) ──┼──► Invoice Pay ──► Utilizes Advance  │
│                     │                                         │
│  Balance (₹0) ─────┴──► PO Closed ──► Complete Audit Trail  │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

### 14.3 PO-Level Control

1. **Single Source of Truth:** Every payment links to PO
2. **Balance Controls:** Prevent overpayment automatically
3. **Advance Utilization:** Auto-adjust when invoice arrives
4. **Status Tracking:** Open → Partial → Paid → Closed

### 14.4 Reporting Clarity

| Report | Contents |
|--------|----------|
| PO Payment Summary | Total, paid, balance at a glance |
| Supplier Ledger | Every transaction with PO reference |
| Advance Report | Unutilized advances with aging |
| Payment Aging | Days since last payment |

### 14.5 Business Benefits

- **Procurement Visibility:** Know total commitment vs payments
- **Cash Flow Control:** Real-time balance awareness
- **Audit Readiness:** Complete trace from PO to payment
- **Supplier Relations:** Clear advance tracking
- **Error Prevention:** Validation rules prevent mistakes
- **Faster Reconciliation:** PO-centric ledger simplifies

---

## Summary: PO-Centric vs Invoice-Centric

```
COMPARISON MATRIX:

| Aspect          | Invoice-Centric         | PO-Centric              |
|----------------|---------------------|------------------------|
| Primary Key    | Invoice             | Purchase Order         |
| Advance       | Disconnected        | Linked to PO           |
| Balance       | Per Invoice         | Per PO                 |
| Overpayment   | Not Detected        | Blocked/Alerted        |
| Reporting     | Fragmented          | Unified                |
| Traceability  | Partial             | End-to-End             |

IMPLEMENTATION PRIORITY:
1. Database Changes
2. POCalculationService
3. Payment Validation
4. UI Redesign
5. Report Generation
6. Testing & Deployment
```

---

**Document Prepared For:** SitePilot ERP Implementation  
**Author:** Senior ERP Architect  
**Date:** April 2026

---

*End of Implementation Plan*