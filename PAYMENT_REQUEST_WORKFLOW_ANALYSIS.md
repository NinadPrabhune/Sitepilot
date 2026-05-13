# Payment Request Workflow Analysis & Mobile API Alignment

**Date:** April 21, 2026  
**Purpose:** Document complete Payment Request workflow to ensure mobile API replicates exact web behavior

---

## Executive Summary

The Payment Request module supports two distinct workflows:
1. **Invoice Payment Requests** - For paying against purchase invoices
2. **PO Advance Requests** - For requesting advances against purchase orders

Both workflows share similar patterns but have different validation rules and calculations. The mobile API controller (`PaymentRequestApiController`) already replicates most web logic from `PaymentRequestController`, but this analysis ensures complete alignment.

---

## 1. Payment Request Listing (Index Flow)

### Data Source

**Primary Table:** `payment_requests`  
**Joins:**
- `purchase_invoices` (for invoice payment requests)
- `purchase_orders` (for PO advance requests)
- `suppliers` (via invoice or PO)
- `users` (requested_by, approved_by)
- `payments_module` (for payment history)

### Fields Shown in UI

| Field | DB Column | Type | Description |
|-------|-----------|------|-------------|
| ID | `id` | integer | Payment request ID |
| Invoice/PO No | `invoice_number` / `po_number` | string | Linked invoice or PO number (clickable link) |
| Supplier | `supplier.name` | string | Supplier name |
| Type | `type` | enum | 'invoice_payment' or 'po_advance' |
| Requested | `requested_amount` | decimal(10,2) | Amount requested |
| Approved | `approved_amount` | decimal(10,2) | Amount approved (null if pending) |
| Status | `status` | enum | Current status |
| Created By | `requested_by` | user | User who created request |
| Created At | `created_at` | datetime | Creation timestamp |
| Invoice Date | `invoice_date` / `po_date` | date | Related invoice or PO date |

### Status Values

| Status | Label | Badge Class | Description |
|--------|-------|-------------|-------------|
| `pending` | Pending | bg-warning text-dark | Awaiting approval |
| `approved` | Approved | bg-success | Fully approved |
| `partially_approved` | Partial | bg-info text-dark | Partially approved |
| `rejected` | Rejected | bg-danger | Rejected by approver |
| `paid` | Paid | bg-success | Fully paid |
| `partially_paid` | Partial Paid | bg-primary | Partially paid |

### Filters Available

| Filter | Parameter | Type | Description |
|--------|-----------|------|-------------|
| Status | `status` | enum | Filter by payment request status |
| Supplier | `supplier_id` | integer | Filter by supplier |
| Start Date | `start_date` | date | Filter by creation date (from) |
| End Date | `end_date` | date | Filter by creation date (to) |
| Project | (implicit) | integer | Filter by active project (site_id) |

### Sorting and Pagination

- **Sorting:** DataTables client-side sorting on all columns
- **Pagination:** DataTables server-side pagination (default 10/25/50/100 entries per page)
- **Default Order:** Created at descending (newest first)

### Computed Fields

| Field | Calculation | Formula |
|-------|-------------|---------|
| `total_paid_amount` | Sum of payments against request | `SUM(payments_module.amount WHERE payment_request_id = X)` |
| `remaining_amount` | Remaining to be paid | `requested_amount - total_paid_amount` |
| `pending_amount` | For invoice context | `grand_total - paid_amount - advance_used - active_requests` |

### Query Logic (PaymentRequestDataTable)

```php
// Base query with workspace and project filtering
$query = PaymentRequest::with(['invoice.supplier', 'requestedBy', 'po.supplier'])
    ->where(function ($q) {
        $q->whereHas('invoice', fn($q) => $q->where('workspace_id', getActiveWorkSpace()))
          ->orWhere(fn($q) => $q->where('type', 'po_advance')->whereNull('purchase_invoice_id'));
    })
    ->when(getActiveProject(), function ($q) {
        $q->where(fn($q) => $q->whereHas('invoice', fn($q) => $q->where('site_id', getActiveProject()))
                          ->orWhere(fn($q) => $q->where('type', 'po_advance')->whereHas('po', fn($q) => $q->where('site_id', getActiveProject()))));
    });
```

---

## 2. Approval Workflow (Approve / Partial Approve / Reject)

### Actions Available

| Action | Description | Endpoint |
|--------|-------------|----------|
| Full Approval | Approve entire requested amount | `POST /payment-request/{id}/approve-single` |
| Partial Approval | Approve portion of requested amount | `POST /payment-request/{id}/approve-single` |
| Reject | Reject payment request | `POST /payment-request/{id}/approve-single` |

### Validation Rules

#### Pre-Approval Checks

1. **Status Check:** Only `pending` requests can be approved
2. **Payment Check:** Cannot approve if payment already created (`hasPayment()`)
3. **Permission Check:** User must have `manage-payment manage` permission
4. **Ownership Check:** (Cross-supplier validation at payment time)

#### Invoice Payment Request Validations

- **Max Allowed:** Always `requested_amount` (never invoice total)
- **Advance Allocation:** Auto-allocate PO advance if not already allocated
- **Financial Period:** Validate period not closed (if feature flag enabled)
- **Direct GRN:** Block if `finance.po_locked_advance_enabled` and no PO linked

#### PO Advance Request Validations

- **Max Allowed:** Always `requested_amount` (never PO grand_total)
- **PO Limit:** Validate total paid won't exceed PO total
- **Active Request:** Only one active advance request per PO allowed

### Approval Amount Logic

| Action | Approved Amount Calculation |
|--------|------------------------------|
| Full Approval | `requested_amount` |
| Partial Approval | `min(approved_amount_input, requested_amount, max_allowed)` |
| Reject | N/A (status change only) |

### Status Transitions

```
pending
  ├─→ approved (full approval)
  ├─→ partially_approved (partial approval)
  └─→ rejected (rejection)

approved
  ├─→ partially_paid (partial payment)
  └─→ paid (full payment)

partially_approved
  ├─→ partially_paid (partial payment)
  └─→ paid (full payment)

partially_paid
  └─→ paid (full payment)
```

### Model-Level Status Transition Validation

```php
// PaymentRequest.php - boot() method
static::updating(function ($model) {
    if ($model->isDirty('status')) {
        $allowed = match ($model->getOriginal('status')) {
            'pending' => ['approved', 'partially_approved', 'rejected'],
            'approved' => ['partially_paid', 'paid'],
            'partially_approved' => ['partially_paid', 'paid'],
            'partially_paid' => ['paid'],
            default => [],
        };
        if (!in_array($model->status, $allowed)) {
            throw new \Exception("Invalid status transition");
        }
    }
});
```

### Audit Fields

| Field | Description | Set When |
|-------|-------------|----------|
| `approved_by` | User ID of approver | On approve/partial/reject |
| `approved_at` | Timestamp of approval | On approve/partial/reject |
| `rejection_reason` | Reason for rejection | On reject (max 500 chars) |
| `approved_amount` | Amount approved | On approve/partial |

### Financial Snapshots (Captured at Approval)

| Snapshot Field | Description | Calculation |
|---------------|-------------|-------------|
| `net_payable_snapshot` | Net payable at approval time | `invoice.getNetPayableWithoutRequests()` |
| `advance_used_snapshot` | Advance utilized at approval time | `invoice.getAdvanceUtilizedForInvoice()` |
| `paid_amount_snapshot` | Amount already paid at approval | `invoice.getActualPaidAmount()` |
| `active_requests_snapshot` | Active request sum at approval | `invoice.getActivePaymentRequestsSum()` |

### Approval Workflow Steps (Invoice Payment)

1. **Lock Records** (in order):
   - PurchaseInvoice (lockForUpdate)
   - PurchaseOrder (if exists, lockForUpdate)
   - PaymentRequest (lockForUpdate)
   - AdvanceUtilizations (if PO exists)

2. **Auto-Allocate Advance** (if not allocated):
   - Call `AdvanceAllocationService->allocateToInvoice()`
   - Or `allocateAdvanceWithoutFeatureFlag()` if flag disabled

3. **Calculate Max Allowed:**
   - Always `paymentRequest->requested_amount`

4. **Process Action:**
   - **Approve:** Set status=approved, approved_amount=requested_amount
   - **Partial:** Set status=partially_approved, approved_amount=input
   - **Reject:** Set status=rejected, rejection_reason=input, release advance reservation

5. **Capture Snapshots:** All 4 financial snapshots

6. **Send Notification:** `NotificationService->createPaymentApprovalNotification()`

7. **Log Audit:** Detailed log to `payment_audit` channel

### Approval Workflow Steps (PO Advance)

1. **Lock Records:**
   - PurchaseOrder (lockForUpdate)
   - PaymentRequest (lockForUpdate)

2. **Calculate Max Allowed:**
   - Always `paymentRequest->requested_amount`

3. **Process Action:**
   - **Approve:** Set status=approved, create supplier advance ledger entry
   - **Partial:** Set status=partially_approved
   - **Reject:** Set status=rejected

4. **Capture Snapshots:** PO-specific snapshots

5. **Send Notification:** Type-aware notification

6. **Log Audit:** Detailed log to `payment_audit` channel

---

## 3. Payment Execution Against Approved Requests

### Payment Creation Flow

**Entry Point:** `PaymentService->createPaymentFromRequest()`  
**Web Route:** `POST /payments-module/store` (with payment_request_id)  
**Mobile API:** (Not yet implemented - needs to be added)

### Payment Amount Source

| Scenario | Amount Source |
|----------|---------------|
| Full Payment | `approved_amount` (or `requested_amount` if null) |
| Partial Payment | Custom amount passed to service (≤ remaining approved) |

### Partial Payment Support

**Yes** - Partial payments are supported:
- Multiple payments can be made against a single approved request
- Each payment must be ≤ remaining approved amount
- Status transitions: approved → partially_paid → paid

### Remaining Balance Calculation

```php
$totalPaid = PaymentsModule::where('payment_request_id', $request->id)->sum('amount');
$approvedAmount = $request->approved_amount ?? $request->requested_amount;
$remainingApproved = $approvedAmount - $totalPaid;
```

### Validation Rules

#### Pre-Payment Checks

1. **Status Check:** Only approved/partially_approved/partially_paid requests can be paid
2. **Fully Paid Check:** Cannot pay if `totalPaid >= approvedAmount`
3. **Amount Check:** Payment amount must be ≤ remaining approved amount
4. **Amount Check:** Payment amount must be > 0
5. **PO Limit Check:** For PO advances, total paid ≤ PO grand_total
6. **Supplier Check:** Supplier in payment must match request's supplier (cross-supplier fraud prevention)

#### Payment Validation Logic

```php
// Step 1: Status validation
$allowedStatuses = [PaymentRequest::STATUS_APPROVED, 
                    PaymentRequest::STATUS_PARTIALLY_APPROVED, 
                    PaymentRequest::STATUS_PARTIALLY_PAID];
if (!in_array($request->status, $allowedStatuses)) {
    throw new \InvalidArgumentException('Only approved requests can be paid');
}

// Step 2: Idempotency check (if idempotency_key provided)
if ($existingPayment = PaymentsModule::where('idempotency_key', $key)->first()) {
    return $existingPayment;
}

// Step 3: Amount validation
$totalPaid = $request->payments()->sum('amount');
$approvedAmount = $request->approved_amount ?? $request->requested_amount;
$remainingApproved = $approvedAmount - $totalPaid;

if ($paymentAmount > $remainingApproved) {
    throw new \InvalidArgumentException('Payment amount exceeds approved limit');
}

// Step 4: PO limit validation (for PO advances)
if ($request->isPoAdvance()) {
    $summary = $this->getPOAdvanceSummary($request->po_id);
    if (($summary['total_paid'] + $paymentAmount) > $summary['total_po_amount']) {
        throw new \InvalidArgumentException('PO limit exceeded');
    }
}
```

### Payment Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `payment_number` | string | Auto-generated | Format: PAY-XXXX |
| `supplier_id` | integer | Yes | Must match request's supplier |
| `purchase_invoice_id` | integer | Conditional | For invoice payments |
| `purchase_order_id` | integer | Conditional | For PO advances |
| `site_id` | integer | Yes | Project/site ID |
| `payment_date` | date | Yes | Date of payment |
| `amount` | decimal(10,2) | Yes | Payment amount |
| `payment_type` | enum | Yes | 'against_invoice' or 'advance_against_po' |
| `mode` | string | No | Payment mode (bank_transfer, etc.) |
| `reference_number` | string | No | Reference number |
| `notes` | string | No | Payment notes |
| `status` | enum | Auto | 'completed' |
| `payment_request_id` | integer | Yes | Linked payment request |
| `idempotency_key` | string | No | For idempotency |

### Database Updates

#### 1. Create Payment Record

```php
PaymentsModule::create([
    'payment_number' => PaymentsModule::generatePaymentNumber(),
    'supplier_id' => $invoice->supplier_id,
    'purchase_invoice_id' => $invoice->id,
    'purchase_order_id' => $invoice->po_id,
    'site_id' => $invoice->site_id,
    'payment_date' => $request->payment_date,
    'amount' => $paymentAmount,
    'payment_type' => PaymentsModule::PAYMENT_TYPE_AGAINST_INVOICE,
    'mode' => $request->mode ?? 'bank_transfer',
    'status' => PaymentsModule::STATUS_COMPLETED,
    'created_by' => auth()->id(),
    'workspace_id' => $invoice->workspace_id,
    'notes' => $request->remarks,
    'payment_request_id' => $request->id,
]);
```

#### 2. Update Payment Request Status

```php
$paymentRequest->updateStatusOnPayment();
// Logic:
// if (totalPaid >= requested_amount) → status = 'paid'
// else if (totalPaid > 0) → status = 'partially_paid'
```

#### 3. Update Invoice Payment Status

```php
$this->updateInvoicePaymentStatus($invoice);
// Updates invoice payment_status based on payments
```

#### 4. Update PO Invoiced Status (if applicable)

```php
if ($invoice->po_id) {
    $po = PurchaseOrder::find($invoice->po_id);
    $po->updateInvoicedStatus();
}
```

#### 5. Create Supplier Ledger Entry

```php
LedgerHelper::createPaymentEntry($payment);
// Creates debit/credit entry in supplier ledger
```

#### 6. Apply Advance Reservation (if feature flag enabled)

```php
if (config('finance.po_locked_advance_enabled', false)) {
    $allocationService->applyReservation($paymentRequest->id);
}
```

### Status Transitions on Payment

| Before Payment | Payment Amount | After Payment |
|----------------|----------------|---------------|
| approved | < approved_amount | partially_paid |
| approved | = approved_amount | paid |
| partially_approved | < approved_amount | partially_paid |
| partially_approved | = approved_amount | paid |
| partially_paid | < remaining | partially_paid |
| partially_paid | = remaining | paid |

### Deadlock Retry Mechanism

The payment service includes a deadlock retry mechanism:
- **Max Attempts:** 3
- **Delay:** 100ms between retries
- **Trigger:** SQL deadlock detected
- **Behavior:** Retry transaction automatically

### Concurrency Protection

**Lock Order (Critical):**
1. PaymentRequest (lockForUpdate)
2. PurchaseInvoice OR PurchaseOrder (lockForUpdate, based on type)
3. AdvanceUtilizations (if PO exists)

This prevents race conditions when multiple users approve/pay simultaneously.

---

## 4. Mobile API Alignment Requirements

### Current Mobile API Endpoints

| Endpoint | Method | Purpose | Web Equivalent |
|----------|--------|---------|----------------|
| `/api/payment-request/{invoice_id}` | GET | Get prefill data for invoice payment request | `createModal()` |
| `/api/payment-request` | POST | Create invoice payment request | `store()` |
| `/api/po-advance-request/{po_id}` | GET | Get prefill data for PO advance request | `advanceRequestModal()` |
| `/api/po-advance-request/{po_id}` | POST | Create PO advance request | `storeAdvanceRequest()` |

### Missing Mobile API Endpoints

| Endpoint | Method | Purpose | Web Equivalent | Priority |
|----------|--------|---------|----------------|----------|
| `/api/payment-request/list` | GET | List payment requests with filters | `index()` | HIGH |
| `/api/payment-request/{id}` | GET | Get payment request details | `show()` | HIGH |
| `/api/payment-request/{id}/approve` | POST | Approve payment request | `approveSingle()` | HIGH |
| `/api/payment-request/{id}/payment` | POST | Create payment against request | `createFromPaymentRequest()` + `store()` | CRITICAL |

### Required Request/Response Formats

#### 1. List Payment Requests

**Request:**
```
GET /api/payment-request/list?page=1&per_page=10&status=pending&supplier_id=123&start_date=2025-01-01&end_date=2025-12-31
```

**Response:**
```json
{
  "success": true,
  "message": "Payment requests retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 789,
        "type": "invoice_payment",
        "invoice_number": "INV-001",
        "po_number": null,
        "supplier_name": "ABC Supplier",
        "requested_amount": 50000.00,
        "approved_amount": null,
        "status": "pending",
        "requested_by": "John Doe",
        "created_at": "2025-04-21T10:30:00Z",
        "invoice_date": "2025-04-15",
        "total_paid": 0,
        "remaining_amount": 50000.00
      }
    ],
    "total": 100,
    "per_page": 10,
    "last_page": 10
  }
}
```

#### 2. Get Payment Request Details

**Request:**
```
GET /api/payment-request/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment request details retrieved successfully",
  "data": {
    "id": 789,
    "type": "invoice_payment",
    "status": "approved",
    "requested_amount": 50000.00,
    "approved_amount": 50000.00,
    "payment_date": "2025-04-25",
    "remarks": "Urgent payment",
    "requested_by": {
      "id": 123,
      "name": "John Doe"
    },
    "approved_by": {
      "id": 456,
      "name": "Jane Smith"
    },
    "approved_at": "2025-04-22T14:00:00Z",
    "invoice": {
      "id": 123,
      "invoice_number": "INV-001",
      "invoice_date": "2025-04-15",
      "grand_total": 100000.00,
      "paid_amount": 20000.00,
      "advance_utilized": 30000.00,
      "remaining_balance": 50000.00
    },
    "po": null,
    "snapshots": {
      "net_payable_snapshot": 50000.00,
      "advance_used_snapshot": 30000.00,
      "paid_amount_snapshot": 20000.00,
      "active_requests_snapshot": 0
    },
    "payments": [
      {
        "id": 456,
        "payment_number": "PAY-0001",
        "amount": 25000.00,
        "payment_date": "2025-04-23",
        "status": "completed"
      }
    ],
    "total_paid": 25000.00,
    "remaining_amount": 25000.00
  }
}
```

#### 3. Approve Payment Request

**Request:**
```
POST /api/payment-request/{id}/approve
Content-Type: application/json

{
  "action": "approve",
  "approved_amount": 50000.00,
  "rejection_reason": null
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment request approved successfully",
  "data": {
    "id": 789,
    "status": "approved",
    "approved_amount": 50000.00,
    "approved_by": {
      "id": 456,
      "name": "Jane Smith"
    },
    "approved_at": "2025-04-22T14:00:00Z"
  }
}
```

#### 4. Create Payment Against Request

**Request:**
```
POST /api/payment-request/{id}/payment
Content-Type: application/json

{
  "amount": 25000.00,
  "payment_date": "2025-04-23",
  "mode": "bank_transfer",
  "reference_number": "REF-12345",
  "notes": "Partial payment",
  "idempotency_key": "unique-key-123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment created successfully",
  "data": {
    "payment_id": 456,
    "payment_number": "PAY-0001",
    "amount": 25000.00,
    "payment_date": "2025-04-23",
    "status": "completed",
    "payment_request": {
      "id": 789,
      "status": "partially_paid",
      "total_paid": 25000.00,
      "remaining_amount": 25000.00
    }
  }
}
```

### Field-Level Mapping (DB → API → Mobile UI)

#### Payment Request Fields

| DB Field | API Field | Mobile UI Label | Type | Format |
|----------|-----------|-----------------|------|--------|
| `id` | `id` | Request ID | integer | - |
| `type` | `type` | Type | enum | 'invoice_payment' / 'po_advance' |
| `status` | `status` | Status | enum | pending/approved/partially_approved/rejected/paid/partially_paid |
| `requested_amount` | `requested_amount` | Requested Amount | decimal | 2 decimal places |
| `approved_amount` | `approved_amount` | Approved Amount | decimal | 2 decimal places (null if pending) |
| `payment_date` | `payment_date` | Payment Date | date | YYYY-MM-DD |
| `remarks` | `remarks` | Remarks | string | max 1000 chars |
| `requested_by` | `requested_by` | Created By | object | {id, name} |
| `approved_by` | `approved_by` | Approved By | object | {id, name} (null if pending) |
| `approved_at` | `approved_at` | Approved At | datetime | ISO 8601 |
| `rejection_reason` | `rejection_reason` | Rejection Reason | string | max 500 chars |
| `created_at` | `created_at` | Created At | datetime | ISO 8601 |
| `paid_at` | `paid_at` | Paid At | datetime | ISO 8601 |
| `net_payable_snapshot` | `snapshots.net_payable` | Net Payable (Snapshot) | decimal | 2 decimal places |
| `advance_used_snapshot` | `snapshots.advance_used` | Advance Used (Snapshot) | decimal | 2 decimal places |
| `paid_amount_snapshot` | `snapshots.paid_amount` | Paid Amount (Snapshot) | decimal | 2 decimal places |
| `active_requests_snapshot` | `snapshots.active_requests` | Active Requests (Snapshot) | decimal | 2 decimal places |

#### Computed Fields (API Only)

| API Field | Calculation | Description |
|-----------|-------------|-------------|
| `total_paid` | SUM(payments.amount) | Total paid against request |
| `remaining_amount` | approved_amount - total_paid | Remaining to be paid |

#### Invoice Fields (Nested)

| DB Field | API Field | Mobile UI Label | Type |
|----------|-----------|-----------------|------|
| `invoice_number` | `invoice.invoice_number` | Invoice Number | string |
| `invoice_date` | `invoice.invoice_date` | Invoice Date | date |
| `grand_total` | `invoice.grand_total` | Invoice Total | decimal |
| `paid_amount` | `invoice.paid_amount` | Already Paid | decimal |
| `advance_utilized` | `invoice.advance_utilized` | Advance Used | decimal |
| `remaining_balance` | `invoice.remaining_balance` | Remaining Balance | decimal |

#### PO Fields (Nested)

| DB Field | API Field | Mobile UI Label | Type |
|----------|-----------|-----------------|------|
| `po_number` | `po.po_number` | PO Number | string |
| `po_date` | `po.po_date` | PO Date | date |
| `grand_total` | `po.grand_total` | PO Total | decimal |
| `total_paid` | `po.total_paid` | Already Paid | decimal |

### Validation Rules (Must Enforce in Mobile)

#### Create Payment Request

| Rule | Error Message |
|------|---------------|
| `purchase_invoice_id` required | Invoice ID is required |
| `requested_amount` >= 0.01 | Amount must be at least 0.01 |
| `requested_amount` <= max_allowed | Amount exceeds remaining balance |
| No pending request exists | A pending payment request already exists |
| Invoice not fully paid | Invoice is already fully paid |
| Direct GRN blocked (if flag enabled) | Direct GRN invoices cannot create payment requests with advance allocation |
| Financial period not closed (if flag enabled) | Financial period is closed |

#### Approve Payment Request

| Rule | Error Message |
|------|---------------|
| Status must be 'pending' | This payment request is not pending approval |
| No payment exists | A payment has already been created for this request |
| `approved_amount` >= 0.01 | Approved amount must be at least 0.01 |
| `approved_amount` <= requested_amount | Cannot approve more than requested amount |
| `rejection_reason` required for reject | Rejection reason is required |

#### Create Payment

| Rule | Error Message |
|------|---------------|
| Status must be approved/partially_approved/partially_paid | Only approved requests can be paid |
| Not fully paid | Payment request is already fully paid |
| `amount` > 0 | Payment amount must be greater than zero |
| `amount` <= remaining_approved | Payment amount exceeds approved limit |
| Supplier matches | Invalid supplier for this payment request |
| PO limit not exceeded (for advances) | PO limit exceeded |

### Edge Cases to Handle

1. **Concurrent Approvals:** Two users approving same request simultaneously
   - Solution: Row locking with lockForUpdate()
   
2. **Idempotency:** Duplicate payment requests/payments
   - Solution: idempotency_key field with unique constraint
   
3. **Deadlocks:** Database deadlocks during payment
   - Solution: Retry mechanism with 3 attempts
   
4. **Advance Allocation:** Auto-allocation on first request
   - Solution: Check and allocate if not already allocated
   
5. **Snapshot Mismatch:** Financial state changes between approval and payment
   - Solution: Use snapshots captured at approval time
   
6. **Partial Payment Overflow:** Multiple partial payments exceeding approved amount
   - Solution: Validate against remaining approved amount each time

---

## 5. State Transition Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    PAYMENT REQUEST LIFECYCLE                     │
└─────────────────────────────────────────────────────────────────┘

                    ┌──────────┐
                    │  START   │
                    └────┬─────┘
                         │
                         ▼
                    ┌──────────┐
                    │ PENDING  │ ◄──────┐
                    └────┬─────┘        │
                         │              │
         ┌───────────────┼──────────────┼──────────────┐
         │               │              │              │
         ▼               ▼              ▼              │
    ┌─────────┐    ┌─────────┐   ┌─────────┐         │
    │ APPROVED│    │ PARTIAL │   │REJECTED │         │
    └────┬────┘    │ APPROVED│   └─────────┘         │
         │         └────┬────┘                       │
         │              │                            │
         │    ┌─────────┴─────────┐                 │
         │    │                   │                 │
         │    ▼                   ▼                 │
         │ ┌─────────┐       ┌─────────┐            │
         │ │ PARTIAL │       │   PAID  │            │
         │ │  PAID   │       └─────────┘            │
         │ └────┬────┘                              │
         │      │                                   │
         │      └──────────┐                        │
         │                 │                        │
         └─────────────────┴────────────────────────┘
                               │
                               ▼
                         ┌──────────┐
                         │   END    │
                         └──────────┘

TRANSITIONS:
- pending → approved (full approval)
- pending → partially_approved (partial approval)
- pending → rejected (rejection)
- approved → partially_paid (partial payment)
- approved → paid (full payment)
- partially_approved → partially_paid (partial payment)
- partially_approved → paid (full payment)
- partially_paid → paid (final payment)
```

---

## 6. Gaps Between Web and Mobile API

### Critical Gaps

| Gap | Description | Impact | Priority |
|-----|-------------|--------|----------|
| **Missing List Endpoint** | No API to list payment requests with filters | Mobile cannot view payment requests | CRITICAL |
| **Missing Detail Endpoint** | No API to get payment request details | Mobile cannot view request details | CRITICAL |
| **Missing Approval Endpoint** | No API to approve/partial/reject requests | Mobile cannot approve requests | CRITICAL |
| **Missing Payment Endpoint** | No API to create payment against request | Mobile cannot make payments | CRITICAL |
| **Missing Bulk Approval** | Web supports bulk approval via approvalUpdate() | Mobile must approve one-by-one | MEDIUM |

### Minor Gaps

| Gap | Description | Impact | Priority |
|-----|-------------|--------|----------|
| **Export Functionality** | Web supports export to CSV/Excel | Mobile cannot export | LOW |
| **Advanced Filters** | Web has additional filters not in API | Limited filtering in mobile | LOW |
| **Real-time Updates** | Web uses DataTables with real-time refresh | Mobile needs manual refresh | LOW |

### Alignment Issues

| Issue | Web Behavior | Mobile API Behavior | Fix Required |
|-------|--------------|---------------------|--------------|
| **Direct GRN Handling** | Blocks if `finance.po_locked_advance_enabled` | Same logic present | None |
| **Advance Allocation** | Auto-allocates on request creation | Same logic present | None |
| **Financial Period Check** | Validates period not closed if flag enabled | Same logic present | None |
| **Idempotency** | Supports idempotency_key | Supports idempotency_key | None |
| **Deadlock Retry** | 3 attempts with 100ms delay | Not yet implemented in payment endpoint | Add to payment endpoint |

---

## 7. Recommended Implementation Priority

### Phase 1: Critical Endpoints (Must Have)

1. **GET /api/payment-request/list** - List payment requests with filters
2. **GET /api/payment-request/{id}** - Get payment request details
3. **POST /api/payment-request/{id}/approve** - Approve/partial/reject request
4. **POST /api/payment-request/{id}/payment** - Create payment against request

### Phase 2: Enhanced Features (Should Have)

1. **POST /api/payment-request/bulk-approve** - Bulk approve multiple requests
2. **GET /api/payment-request/{id}/history** - Get approval/payment history
3. **GET /api/payment-request/stats** - Get dashboard statistics

### Phase 3: Nice to Have

1. **GET /api/payment-request/export** - Export to CSV/Excel
2. **WebSocket integration** - Real-time status updates
3. **Offline support** - Cache payment requests for offline viewing

---

## 8. Testing Checklist

### Unit Tests

- [ ] Payment request creation validation
- [ ] Approval workflow (approve, partial, reject)
- [ ] Payment creation validation
- [ ] Status transition validation
- [ ] Amount calculation accuracy
- [ ] Idempotency handling
- [ ] Concurrent approval handling

### Integration Tests

- [ ] End-to-end payment request lifecycle
- [ ] Mobile API vs web logic parity
- [ ] Database transaction rollback on error
- [ ] Ledger entry creation
- [ ] Notification sending

### Edge Case Tests

- [ ] Direct GRN blocking
- [ ] Financial period blocking
- [ ] PO advance limit validation
- [ ] Cross-supplier fraud prevention
- [ ] Deadlock retry mechanism
- [ ] Partial payment overflow

---

## 9. Security Considerations

### Permission Checks

All endpoints must verify:
- `manage-payment manage` permission for create/approve/pay
- Workspace isolation (user's workspace matches request's workspace)
- Project isolation (user's active project matches request's site_id)

### Data Protection

- Sensitive financial data requires authentication
- Audit logging for all financial operations
- Idempotency to prevent duplicate transactions
- Row locking to prevent race conditions

### Fraud Prevention

- Cross-supplier validation (payment supplier must match request supplier)
- PO limit validation (total paid ≤ PO total)
- Amount validation (payment ≤ approved amount)
- Status validation (cannot pay rejected requests)

---

## 10. Conclusion

The web Payment Request workflow is well-designed with:
- Clear status transitions
- Robust validation rules
- Financial snapshots for audit trail
- Concurrency protection via row locking
- Idempotency support
- Deadlock retry mechanism

The mobile API controller already replicates most creation logic. The critical gaps are:
1. **Listing endpoint** - To view payment requests
2. **Detail endpoint** - To view request details
3. **Approval endpoint** - To approve requests
4. **Payment endpoint** - To make payments

Once these endpoints are implemented with the exact same business logic as the web, the mobile API will be fully aligned with the web workflow.

---

## Appendix A: Database Schema

### payment_requests Table

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | NO | AUTO_INCREMENT | Primary key |
| `idempotency_key` | varchar(64) | YES | NULL | Idempotency key |
| `po_id` | bigint | YES | NULL | Linked PO (for advances) |
| `purchase_invoice_id` | bigint | YES | NULL | Linked invoice (for payments) |
| `requested_amount` | decimal(10,2) | NO | 0.00 | Amount requested |
| `approved_amount` | decimal(10,2) | YES | NULL | Amount approved |
| `payment_date` | date | NO | NULL | Requested payment date |
| `status` | varchar(50) | NO | 'pending' | Current status |
| `rejection_reason` | text | YES | NULL | Reason for rejection |
| `remarks` | text | YES | NULL | User remarks |
| `requested_by` | bigint | NO | NULL | User who created |
| `approved_by` | bigint | YES | NULL | User who approved |
| `approved_at` | timestamp | YES | NULL | Approval timestamp |
| `paid_at` | timestamp | YES | NULL | Payment completion timestamp |
| `net_payable_snapshot` | decimal(10,2) | YES | NULL | Financial snapshot |
| `advance_used_snapshot` | decimal(10,2) | YES | NULL | Financial snapshot |
| `paid_amount_snapshot` | decimal(10,2) | YES | NULL | Financial snapshot |
| `active_requests_snapshot` | decimal(10,2) | YES | NULL | Financial snapshot |
| `type` | varchar(50) | YES | 'invoice_payment' | Request type |
| `workspace_id` | bigint | NO | NULL | Workspace ID |
| `transaction_flow_id` | varchar(255) | YES | NULL | Transaction flow ID |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | Update timestamp |

### payments_module Table (Relevant Columns)

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `payment_number` | varchar(50) | Payment number (PAY-XXXX) |
| `payment_request_id` | bigint | Linked payment request |
| `supplier_id` | bigint | Supplier ID |
| `purchase_invoice_id` | bigint | Linked invoice |
| `purchase_order_id` | bigint | Linked PO |
| `site_id` | bigint | Project/site ID |
| `payment_date` | date | Payment date |
| `amount` | decimal(10,2) | Payment amount |
| `payment_type` | varchar(50) | Type: against_invoice, advance_against_po, etc. |
| `mode` | varchar(255) | Payment mode |
| `reference_number` | varchar(255) | Reference number |
| `notes` | text | Payment notes |
| `status` | varchar(50) | Payment status |
| `idempotency_key` | varchar(64) | Idempotency key |

---

## Appendix B: Feature Flags

| Flag | Default | Description | Impact |
|------|---------|-------------|--------|
| `finance.po_locked_advance_enabled` | false | Enables PO-locked advance allocation | Blocks direct GRN requests if true |
| `finance.financial_period_locking_enabled` | false | Enables financial period validation | Blocks payments for closed periods |

---

**Document Version:** 1.0  
**Last Updated:** April 21, 2026  
**Author:** Cascade AI Assistant
