# Ledger System Testing Guide

## Structural Checks (Completed ✓)

All structural checks passed:
- ✓ Time ordering (transaction_date, id) confirmed
- ✓ Cross-site unique constraint working
- ✓ Payment enforcement guard exists
- ✓ Batch update logic (CASE WHEN) exists
- ✓ Deadlock retry logic exists
- ✓ Correlation ID logging exists
- ✓ idempotency_key columns exist
- ✓ Unique constraint exists

## Runtime Tests (Manual Testing Required)

### Tinker Helper Functions

Add this to your Tinker session for quick verification:

```php
// Quick ledger view
function ledger($supplierId = null, $siteId = null) {
    return \App\Models\SupplierTransaction::query()
        ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
        ->when($siteId, fn($q) => $q->where('site_id', $siteId))
        ->orderBy('transaction_date')
        ->orderBy('id')
        ->get(['id','reference_type','reference_id','debit','credit','balance','transaction_date']);
}

// Check for duplicates
function checkDuplicates() {
    return \Illuminate\Support\Facades\DB::select("
        SELECT reference_type, reference_id, supplier_id, site_id, COUNT(*) as count, GROUP_CONCAT(id) as ids
        FROM supplier_transactions
        GROUP BY reference_type, reference_id, supplier_id, site_id
        HAVING count > 1
    ");
}

// Get current balance
function getBalance($supplierId, $siteId = null) {
    return \App\Models\SupplierTransaction::getCurrentBalance($supplierId, $siteId);
}
```

### Test 1: Multi-Invoice Partial Payment (Tinker)

**Goal:** One payment → multiple invoices, only ONE ledger entry

**Run in Tinker:**
```php
use App\Models\PurchaseInvoice;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

// Create test invoices
$invoiceA = PurchaseInvoice::create([
    'supplier_id' => 1,
    'site_id' => 1,
    'grand_total' => 100000,
    'invoice_date' => now(),
    'invoice_number' => 'TEST-A-' . time(),
    'created_by' => 1,
    'workspace_id' => 1,
    'payment_status' => 'unpaid',
]);

$invoiceB = PurchaseInvoice::create([
    'supplier_id' => 1,
    'site_id' => 1,
    'grand_total' => 200000,
    'invoice_date' => now(),
    'invoice_number' => 'TEST-B-' . time(),
    'created_by' => 1,
    'workspace_id' => 1,
    'payment_status' => 'unpaid',
]);

// Create partial payment across both
app(PaymentService::class)->createAgainstInvoice([
    'supplier_id' => 1,
    'site_id' => 1,
    'amount' => 150000,
    'payment_type' => 'against_invoice',
    'payment_date' => now(),
    'mode' => 'bank_transfer',
    'invoices' => [
        ['invoice_id' => $invoiceA->id, 'amount' => 50000],
        ['invoice_id' => $invoiceB->id, 'amount' => 100000],
    ],
]);
```

**Verify:**
```php
use App\Models\SupplierTransaction;

SupplierTransaction::where('reference_type', 'payment')->get();
```

**Expected:**
- Only 1 entry
- credit = 150000

### Test 2: Advance → Adjustment Flow (Tinker)

**Goal:** Advance created → invoice created → adjustment, NO new ledger entry during adjustment

**Run in Tinker:**
```php
use App\Services\PaymentService;

// Step 1: Advance
$advance = app(PaymentService::class)->create([
    'supplier_id' => 1,
    'site_id' => 1,
    'amount' => 500000,
    'payment_type' => 'advance_against_po',
    'payment_date' => now(),
    'mode' => 'bank_transfer',
]);

// Step 2: Invoice
$invoice = \App\Models\PurchaseInvoice::create([
    'supplier_id' => 1,
    'site_id' => 1,
    'grand_total' => 300000,
    'invoice_date' => now(),
    'invoice_number' => 'TEST-INV-' . time(),
    'created_by' => 1,
    'workspace_id' => 1,
    'payment_status' => 'unpaid',
]);

// Step 3: Adjust advance (use your actual method)
app(PaymentService::class)->adjustAdvance($advance->id, $invoice->id, 300000);
```

**Verify:**
```php
SupplierTransaction::all();
```

**Expected:**
- 1 advance entry (credit 500000)
- 1 invoice entry (debit 300000)
- NO new payment entry

### Test 3: Simulated Failure Before Ledger Write (Tinker)

**Goal:** Force exception → ensure full rollback

**Temporary Change (IMPORTANT):**
Inside PaymentService before calling LedgerService, add:
```php
throw new \Exception('Test failure before ledger write');
```

**Run in Tinker:**
```php
use App\Services\PaymentService;

try {
    app(PaymentService::class)->create([
        'supplier_id' => 1,
        'site_id' => 1,
        'amount' => 100000,
        'payment_type' => 'against_invoice',
        'payment_date' => now(),
        'mode' => 'bank_transfer',
    ]);
} catch (\Exception $e) {
    echo $e->getMessage();
}
```

**Verify:**
```php
use App\Models\PaymentsModule;
use App\Models\SupplierTransaction;

PaymentsModule::latest()->first(); // should NOT exist
SupplierTransaction::latest()->first(); // should NOT exist
```

**Expected:**
- No payment record
- No ledger entry

**Important:** Remove the temporary exception after test.

### Test 4: Parallel Payment Requests (Same Invoice)

**Objective:** Verify no duplicate ledger entries when 10 concurrent payment requests hit the same invoice.

**Steps:**
1. Create a test invoice with amount ₹1,00,000
2. Use a load testing tool (Apache Bench, JMeter, or custom script) to send 10 simultaneous payment requests
3. Verify only 1 ledger entry exists for the payment
4. Verify balance is correct (₹1,00,000 credit)

**Expected Result:**
- Only 1 payment ledger entry created
- Balance reflects single payment
- No deadlock errors

**Load Test Script:**
```bash
# Using Apache Bench
ab -n 10 -c 10 -p payment.json -T application/json http://localhost/api/payment
```

### Test 2: Idempotency Key Retry

**Objective:** Verify same idempotency_key returns existing record instead of creating duplicate.

**Steps:**
1. Send PO creation request with idempotency_key="test-123"
2. Note the returned PO ID
3. Send exact same request again with same idempotency_key
4. Verify same PO ID is returned (200 status, not 201)

**Expected Result:**
- First request: 201 Created with new PO
- Second request: 200 OK with existing PO (no new record created)

### Test 3: Multi-Invoice Partial Payment

**Objective:** Verify single payment split across multiple invoices doesn't create duplicate ledger entries.

**Steps:**
1. Create Invoice A: ₹1,00,000
2. Create Invoice B: ₹2,00,000
3. Create payment of ₹1,50,000 allocated to both invoices
4. Check ledger entries

**Expected Result:**
- Only 1 payment ledger entry (₹1,50,000 credit)
- Internal allocation handled in payment tables, not ledger
- Balance = ₹1,50,000 remaining

### Test 4: Advance Adjustment Flow

**Objective:** Verify advance adjustment doesn't create new payment ledger entry.

**Steps:**
1. Create advance payment: ₹5,00,000
2. Create invoice: ₹3,00,000
3. Adjust advance against invoice
4. Check ledger entries

**Expected Result:**
- Advance ledger entry exists (₹5,00,000 credit)
- Invoice ledger entry exists (₹3,00,000 debit)
- NO new payment ledger entry during adjustment
- Balance reflects correctly

### Test 5: Simulated Failure Before Ledger Write

**Objective:** Verify transaction rollback works correctly.

**Steps:**
1. Add temporary exception in PaymentService before ledger write
2. Create payment request
3. Verify payment record NOT created
4. Verify ledger entry NOT created
5. Remove exception

**Expected Result:**
- Both payment and ledger rolled back
- No orphan records

## New Features Added

### 1. Payment Reversal Method

**Location:** `LedgerService::reversePaymentEntry()`

**Usage:**
```php
app(LedgerService::class)->reversePaymentEntry($payment, 'Duplicate payment');
```

**Behavior:**
- Creates ADJUSTMENT entry with debit (negates original credit)
- Links to original payment via meta.reversal_of
- Never deletes ledger rows (audit-safe)
- Includes reason and original payment number

### 2. Rebuild Ledger Command

**Command:** `php artisan ledger:rebuild`

**Options:**
- `--supplier-id=ID` - Rebuild for specific supplier
- `--site-id=ID` - Rebuild for specific site

**Usage:**
```bash
# Rebuild all ledgers
php artisan ledger:rebuild

# Rebuild for specific supplier
php artisan ledger:rebuild --supplier-id=123

# Rebuild for specific site
php artisan ledger:rebuild --site-id=456
```

**Behavior:**
- Recalculates all running balances from scratch
- Uses batch update (CASE WHEN) for performance
- Transaction-safe
- Progress bar display

## Load Test Script

Create `load_test_payments.php`:
```php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$invoiceId = 1; // Your test invoice ID
$parallelRequests = 10;

$ch = [];
$mh = curl_multi_init();

for ($i = 0; $i < $parallelRequests; $i++) {
    $ch[$i] = curl_init();
    curl_setopt($ch[$i], CURLOPT_URL, 'http://localhost/api/payment');
    curl_setopt($ch[$i], CURLOPT_POST, 1);
    curl_setopt($ch[$i], CURLOPT_POSTFIELDS, json_encode([
        'invoice_id' => $invoiceId,
        'amount' => 50000,
        'payment_type' => 'against_invoice',
    ]));
    curl_setopt($ch[$i], CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_multi_add_handle($mh, $ch[$i]);
}

$active = null;
do {
    curl_multi_exec($mh, $active);
} while ($active);

foreach ($ch as $c) {
    curl_multi_remove_handle($mh, $c);
    curl_close($c);
}

curl_multi_close($mh);

echo "Sent $parallelRequests parallel requests\n";
```

## Verification Checklist

After testing, verify:

- [ ] No duplicate ledger entries in supplier_transactions table
- [ ] All balances are correct (use ledger:rebuild to verify)
- [ ] Payment reversals create ADJUSTMENT entries
- [ ] Idempotency keys prevent duplicates
- [ ] Deadlock retry works (check logs)
- [ ] Correlation IDs appear in logs
- [ ] Transaction rollback works (no orphans)

## Production Deployment Checklist

- [ ] Run all manual tests above
- [ ] Backup database before migration
- [ ] Run migrations in staging first
- [ ] Monitor logs for deadlock warnings
- [ ] Test with realistic load
- [ ] Document reversal process for operations team
- [ ] Train team on ledger:rebuild command
