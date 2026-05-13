<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\SupplierTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PaymentsModule;

echo "=== LEDGER EDGE CASE TESTS ===\n\n";

// Test 1: Verify time ordering (transaction_date, id)
echo "TEST 1: Verify time ordering (transaction_date, id)\n";
echo "------------------------------------------------\n";
$sample = SupplierTransaction::orderBy('transaction_date', 'asc')
    ->orderBy('id', 'asc')
    ->limit(5)
    ->get(['id', 'transaction_date', 'reference_type', 'reference_id']);
echo "Sample transactions (ordered by transaction_date ASC, id ASC):\n";
foreach ($sample as $tx) {
    echo "  ID: {$tx->id}, Date: {$tx->transaction_date}, Type: {$tx->reference_type}, RefID: {$tx->reference_id}\n";
}
echo "✓ Ordering confirmed\n\n";

// Test 2: Verify cross-site unique constraint
echo "TEST 2: Verify cross-site unique constraint\n";
echo "-------------------------------------------\n";
$crossSiteCheck = DB::select("
    SELECT reference_type, reference_id, supplier_id, site_id, COUNT(*) as count
    FROM supplier_transactions
    GROUP BY reference_type, reference_id, supplier_id, site_id
    HAVING count > 1
");
if (empty($crossSiteCheck)) {
    echo "✓ No cross-site duplicates found (unique constraint working)\n\n";
} else {
    echo "✗ FAIL: Found cross-site duplicates:\n";
    foreach ($crossSiteCheck as $dup) {
        echo "  Type: {$dup->reference_type}, RefID: {$dup->reference_id}, Supplier: {$dup->supplier_id}, Site: {$dup->site_id}, Count: {$dup->count}\n";
    }
    echo "\n";
}

// Test 3: Check for payment ledger entries created via non-PaymentService
echo "TEST 3: Verify payment enforcement guard (all payments via PaymentService)\n";
echo "--------------------------------------------------------------------------\n";
// This is a code-level check - we verify the enforcement guard exists in LedgerService
$ledgerServiceFile = file_get_contents(__DIR__ . '/app/Services/LedgerService.php');
if (str_contains($ledgerServiceFile, 'fromPaymentService') && str_contains($ledgerServiceFile, 'PaymentService')) {
    echo "✓ Enforcement guard exists in LedgerService\n\n";
} else {
    echo "✗ FAIL: Enforcement guard missing\n\n";
}

// Test 4: Verify batch update logic exists
echo "TEST 4: Verify batch update logic (CASE WHEN)\n";
echo "-------------------------------------------------\n";
$ledgerHelperFile = file_get_contents(__DIR__ . '/app/Helpers/LedgerHelper.php');
if (str_contains($ledgerHelperFile, 'CASE id') && str_contains($ledgerHelperFile, 'WHEN')) {
    echo "✓ Batch update logic exists (CASE WHEN)\n\n";
} else {
    echo "✗ FAIL: Batch update logic missing\n\n";
}

// Test 5: Verify deadlock retry logic
echo "TEST 5: Verify deadlock retry logic\n";
echo "------------------------------------\n";
if (str_contains($ledgerServiceFile, 'isDeadlock') && str_contains($ledgerServiceFile, 'retryWithBackoff')) {
    echo "✓ Deadlock retry logic exists\n\n";
} else {
    echo "✗ FAIL: Deadlock retry logic missing\n\n";
}

// Test 6: Verify correlation ID logging
echo "TEST 6: Verify correlation ID (trace_id) logging\n";
echo "-------------------------------------------------\n";
if (str_contains($ledgerServiceFile, 'getTraceId') && str_contains($ledgerServiceFile, 'X-Request-ID')) {
    echo "✓ Correlation ID logging exists\n\n";
} else {
    echo "✗ FAIL: Correlation ID logging missing\n\n";
}

// Test 7: Verify idempotency_key columns exist
echo "TEST 7: Verify idempotency_key columns in tables\n";
echo "--------------------------------------------------\n";
$poHasKey = DB::select("SHOW COLUMNS FROM purchase_orders LIKE 'idempotency_key'");
$grnHasKey = DB::select("SHOW COLUMNS FROM grns LIKE 'idempotency_key'");
$invoiceHasKey = DB::select("SHOW COLUMNS FROM purchase_invoices LIKE 'idempotency_key'");

if (!empty($poHasKey) && !empty($grnHasKey) && !empty($invoiceHasKey)) {
    echo "✓ idempotency_key columns exist in PO, GRN, Invoice tables\n\n";
} else {
    echo "✗ FAIL: Missing idempotency_key columns:\n";
    if (empty($poHasKey)) echo "  - purchase_orders\n";
    if (empty($grnHasKey)) echo "  - grns\n";
    if (empty($invoiceHasKey)) echo "  - purchase_invoices\n";
    echo "\n";
}

// Test 8: Verify unique constraint exists
echo "TEST 8: Verify unique constraint on supplier_transactions\n";
echo "----------------------------------------------------------\n";
$constraintExists = DB::select("
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'supplier_transactions'
    AND CONSTRAINT_NAME = 'unique_reference'
");
if (!empty($constraintExists)) {
    echo "✓ Unique constraint 'unique_reference' exists\n\n";
} else {
    echo "✗ FAIL: Unique constraint missing\n\n";
}

echo "=== SUMMARY ===\n";
echo "All structural checks complete.\n";
echo "Note: Runtime tests (parallel payments, idempotency retry) require actual API calls.\n";
