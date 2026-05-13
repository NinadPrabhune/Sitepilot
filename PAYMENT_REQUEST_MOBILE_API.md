# Payment Request Mobile API Documentation

## Overview

This document describes the Mobile API endpoints for Payment Request functionality. These endpoints replicate the exact business logic from the web implementation to ensure consistency across platforms.

## Two Payment Request Flows

There are TWO distinct payment request flows in the system:

### 1. Invoice Payment Request Flow
- **Purpose**: Request payment for a specific purchase invoice
- **Trigger**: Button with route `payment-request.create-modal` and invoice ID
- **Type**: `TYPE_INVOICE_PAYMENT`
- **Web Controller**: `PaymentRequestController@createModal` and `@store`

### 2. PO Advance Request Flow
- **Purpose**: Request advance payment against a Purchase Order
- **Trigger**: Button with class `po-advance-request-btn` and `data-po-id`
- **Type**: `TYPE_PO_ADVANCE`
- **Web Controller**: `PurchaseOrderController@advanceRequestModal` and `@storeAdvanceRequest`

---

## Authentication

All endpoints require authentication via Laravel Sanctum. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your_token}
```

## Permissions

User must have `manage-payment manage` permission to access these endpoints.

---

# Flow 1: Invoice Payment Request

## Endpoints

### 1. Get Payment Request Data (Prefill)

Retrieves invoice details with calculated amounts for payment request creation. This replicates the logic from `PaymentRequestController@createModal`.

**Endpoint:** `GET /api/payment-request/{invoice_id}`

**URL Parameters:**
- `invoice_id` (integer, required) - The ID of the purchase invoice

**Response:**
```json
{
  "success": true,
  "message": "Payment request data retrieved successfully",
  "data": {
    "invoice_id": 123,
    "invoice_number": "INV-001",
    "invoice_date": "2024-01-15",
    "supplier_name": "ABC Supplier",
    "site_name": "Project A",
    "grand_total": 50000.00,
    "paid_amount": 20000.00,
    "advance_utilized": 10000.00,
    "active_requests": 5000.00,
    "remaining_balance": 15000.00,
    "max_allowed_amount": 15000.00,
    "suggested_amount": 15000.00,
    "po_id": 456,
    "po_number": "PO-001",
    "po_advance_total": 20000.00,
    "po_advance_used": 10000.00,
    "po_advance_remaining": 10000.00,
    "payment_terms": "Net 30 days"
  }
}
```

**Error Responses:**
- `403` - Permission denied
- `404` - Invoice not found
- `500` - Server error

**Business Logic:**
- Calculates paid amount (excluding advance_against_po payments)
- Calculates advance utilized from advance_utilizations (status="applied")
- Calculates active payment requests (status in pending/approved/partially_approved)
- Performs PO advance calculations (both new supplier_advances and legacy payments_module)
- Auto-allocates advance if PO has available advance and invoice has no advance allocated yet
- Respects feature flags: `finance.po_locked_advance_enabled`

---

### 2. Create Payment Request

Creates a new payment request for an invoice. This replicates the exact business logic from `PaymentRequestController@store`.

**Endpoint:** `POST /api/payment-request`

**Request Body:**
```json
{
  "purchase_invoice_id": 123,
  "requested_amount": 15000.00,
  "payment_date": "2024-01-20",
  "remarks": "Advance payment request",
  "idempotency_key": "unique-key-123"
}
```

**Parameters:**
- `purchase_invoice_id` (integer, required) - The ID of the purchase invoice
- `requested_amount` (numeric, required, min: 0.01) - The amount being requested
- `payment_date` (date, required) - The payment date
- `remarks` (string, optional, max: 1000) - Remarks for the payment request
- `idempotency_key` (string, optional, max: 64) - Unique key to prevent duplicate requests

**Response:**
```json
{
  "success": true,
  "message": "Payment request created successfully.",
  "data": {
    "id": 789,
    "status": "pending"
  }
}
```

**Error Responses:**
- `403` - Permission denied
- `422` - Validation error or business rule violation
- `500` - Server error

**Business Rules:**
1. **Direct GRN Hard Stop** (if feature flag enabled): Direct GRN invoices cannot create payment requests with advance allocation
2. **Idempotency Check**: Prevents duplicate requests using idempotency_key
3. **Financial Period Validation** (if feature flag enabled): Validates financial period is not closed
4. **Invoice Not Fully Paid**: Cannot create payment request for fully paid invoice
5. **No Pending Payment Request**: Only one pending payment request per invoice
6. **Amount Validation**: Requested amount cannot exceed remaining balance
7. **Advance Allocation**: Automatically allocates PO advance to invoice if available
8. **Financial Snapshots**: Captures financial state at creation time
9. **Notifications**: Sends notification to approvers

**Transaction Locking:**
- Locks PurchaseInvoice
- Locks PurchaseOrder (if exists)
- Locks AdvanceUtilizations (if exists)

**Feature Flags:**
- `finance.po_locked_advance_enabled`: Controls Direct GRN hard stop and advance allocation service
- `finance.financial_period_locking_enabled`: Controls financial period validation

---

## Testing Examples

### Example 1: Get Payment Request Data

```bash
curl -X GET "https://your-domain.com/api/payment-request/123" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### Example 2: Create Payment Request

```bash
curl -X POST "https://your-domain.com/api/payment-request" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "purchase_invoice_id": 123,
    "requested_amount": 15000.00,
    "payment_date": "2024-01-20",
    "remarks": "Advance payment request",
    "idempotency_key": "unique-key-123"
  }'
```

### Example 3: Test with Already Fully Paid Invoice

```bash
curl -X POST "https://your-domain.com/api/payment-request" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "purchase_invoice_id": 456,
    "requested_amount": 1000.00,
    "payment_date": "2024-01-20"
  }'

# Expected Response: 422 - "Invoice is already fully paid."
```

### Example 4: Test with Invalid Amount

```bash
curl -X POST "https://your-domain.com/api/payment-request" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "purchase_invoice_id": 123,
    "requested_amount": 999999.00,
    "payment_date": "2024-01-20"
  }'

# Expected Response: 422 - "Requested amount cannot exceed remaining invoice amount. Maximum allowed: ₹15,000.00"
```

---

# Flow 2: PO Advance Request

## Endpoints

### 1. Get PO Advance Request Data (Prefill)

Retrieves PO details with calculated amounts for PO advance request creation. This replicates the logic from `PurchaseOrderController@advanceRequestModal`.

**Endpoint:** `GET /api/po-advance-request/{po_id}`

**URL Parameters:**
- `po_id` (integer, required) - The ID of the purchase order

**Response:**
```json
{
  "success": true,
  "message": "PO advance request data retrieved successfully",
  "data": {
    "po": {
      "id": 456,
      "po_number": "PO-001",
      "po_date": "2024-01-10",
      "grand_total": 100000.00,
      "status": "Approved"
    },
    "supplier": {
      "id": 123,
      "name": "ABC Supplier"
    },
    "site": {
      "id": 789,
      "name": "Project A"
    },
    "grand_total": 100000.00,
    "existing_advances": 20000.00,
    "pending_advances": 10000.00,
    "available_balance": 70000.00,
    "payment_terms_conditions": "Net 30 days"
  }
}
```

**Error Responses:**
- `403` - Permission denied
- `404` - PO not found
- `500` - Server error

**Business Logic:**
- Calculates existing advances (approved/partially_approved/paid PO advances)
- Calculates pending advances (pending PO advances)
- Calculates available balance = grand_total - existing_advances - pending_advances
- Returns PO, supplier, site details
- Returns payment terms & conditions

---

### 2. Create PO Advance Request

Creates a new PO advance request. This replicates the exact business logic from `PurchaseOrderController@storeAdvanceRequest`.

**Endpoint:** `POST /api/po-advance-request/{po_id}`

**URL Parameters:**
- `po_id` (integer, required) - The ID of the purchase order

**Request Body:**
```json
{
  "percentage": 10,
  "advance_amount": 10000.00,
  "payment_date": "2024-01-20",
  "notes": "Initial advance for material procurement"
}
```

**Parameters:**
- `percentage` (integer, required, min: 1, max: 100) - The percentage of PO total
- `advance_amount` (numeric, required, min: 0) - The advance amount
- `payment_date` (date, required) - The payment date
- `notes` (string, optional, max: 1000) - Notes for the advance request

**Response:**
```json
{
  "success": true,
  "message": "Advance request created successfully",
  "data": {
    "id": 789,
    "status": "pending"
  }
}
```

**Error Responses:**
- `403` - Permission denied
- `422` - Validation error or business rule violation
- `500` - Server error

**Business Rules:**
1. **PO Payment Completed Check**: Cannot request advance if PO is fully paid
2. **Active Advance Request Check**: Only one active advance request allowed per PO
3. **Percentage Range**: Must be between 1 and 100
4. **Amount Calculation**: Advance amount must match calculated amount (PO total * percentage / 100)
5. **Amount <= PO Total**: Advance amount cannot exceed PO total
6. **Available Balance Check**: Advance amount cannot exceed available balance
7. **Pending Requests Check**: Total pending advances + new request must not exceed PO total
8. **Transaction Locking**: Locks PO and payment_requests to prevent race conditions

**Transaction Locking:**
- Locks PurchaseOrder
- Locks payment_requests rows for the PO

---

## Testing Examples for PO Advance

### Example 1: Get PO Advance Request Data

```bash
curl -X GET "https://your-domain.com/api/po-advance-request/456" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### Example 2: Create PO Advance Request

```bash
curl -X POST "https://your-domain.com/api/po-advance-request/456" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "percentage": 10,
    "advance_amount": 10000.00,
    "payment_date": "2024-01-20",
    "notes": "Initial advance for material procurement"
  }'
```

### Example 3: Test with Already Fully Paid PO

```bash
curl -X POST "https://your-domain.com/api/po-advance-request/789" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "percentage": 10,
    "advance_amount": 5000.00,
    "payment_date": "2024-01-20"
  }'

# Expected Response: 422 - "Payment already completed for this PO. Cannot request advance."
```

### Example 4: Test with Invalid Percentage

```bash
curl -X POST "https://your-domain.com/api/po-advance-request/456" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "percentage": 150,
    "advance_amount": 150000.00,
    "payment_date": "2024-01-20"
  }'

# Expected Response: 422 - "Percentage must be between 1 and 100."
```

---

## Implementation Notes

### Code Reuse
The API controller reuses the exact business logic from the web controller:
- Same validation rules
- Same permission checks
- Same calculations (PO advance, advance allocation, max allowed)
- Same transaction locking strategy
- Same notification service

### Key Methods
- `getPaymentRequestData($invoiceId)`: Replicates `createModal` logic
- `store(Request $request)`: Replicates `store` logic
- `calculateMaxAllowedForPR()`: Same calculation as web controller
- `allocateAdvanceWithoutFeatureFlag()`: Handles advance allocation when feature flag disabled
- `lockAdvanceUtilizationsForInvoice()`: Prevents race conditions

### Logging
All payment request creations are logged to `payment_audit` channel with:
- Payment request details
- Financial snapshots
- Source indicator (`mobile_api`)
- Advance allocation status

### Error Handling
- All exceptions are caught and logged
- User-friendly error messages returned
- Notification failures don't fail the payment request creation

---

## Comparison with Web Implementation

| Feature | Web | Mobile API |
|---------|-----|------------|
| Validation Rules | ✅ Same | ✅ Same |
| Permission Checks | ✅ Same | ✅ Same |
| PO Advance Calculation | ✅ Same | ✅ Same |
| Advance Allocation | ✅ Same | ✅ Same |
| Transaction Locking | ✅ Same | ✅ Same |
| Financial Snapshots | ✅ Same | ✅ Same |
| Notifications | ✅ Same | ✅ Same |
| Idempotency | ✅ Same | ✅ Same |
| Feature Flags | ✅ Same | ✅ Same |
| Direct GRN Hard Stop | ✅ Same | ✅ Same |
| Financial Period Validation | ✅ Same | ✅ Same |

---

## Files Modified

1. **Controller**: `app/Http/Controllers/Api/PaymentRequestApiController.php`
   - Moved to correct namespace (Api)
   - Added `getPaymentRequestData()` method
   - Added `store()` method
   - Added helper methods for advance allocation and calculations

2. **Routes**: `routes/api.php`
   - Added import for `PaymentRequestApiController`
   - Added GET route: `/api/payment-request/{invoice_id}`
   - Added POST route: `/api/payment-request`

---

## Next Steps

1. Test the API endpoints with valid invoice data
2. Test edge cases (fully paid invoice, invalid amount, etc.)
3. Update mobile app to use these endpoints
4. Monitor logs for any issues in production
