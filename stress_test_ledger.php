<?php

/**
 * Ledger Stress Test Script
 * Simulates 100+ parallel payment requests to test concurrency safety
 * 
 * Usage: php stress_test_ledger.php --invoice-id=1 --requests=100
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Parse command line arguments
$options = getopt('', ['invoice-id:', 'requests:', 'url:']);
$invoiceId = $options['invoice-id'] ?? 1;
$parallelRequests = (int)($options['requests'] ?? 100);
$baseUrl = $options['url'] ?? 'http://localhost';

echo "=== LEDGER STRESS TEST ===\n";
echo "Invoice ID: {$invoiceId}\n";
echo "Parallel Requests: {$parallelRequests}\n";
echo "Base URL: {$baseUrl}\n\n";

// Get invoice details
$invoice = \App\Models\PurchaseInvoice::find($invoiceId);
if (!$invoice) {
    echo "ERROR: Invoice {$invoiceId} not found\n";
    exit(1);
}

echo "Invoice Amount: ₹" . number_format($invoice->grand_total, 2) . "\n";
echo "Supplier ID: {$invoice->supplier_id}\n";
echo "Site ID: {$invoice->site_id}\n\n";

// Count existing ledger entries before test
$ledgerBefore = \App\Models\SupplierTransaction::where('reference_type', 'payment')
    ->where('reference_id', '>', 0)
    ->count();
echo "Existing payment ledger entries: {$ledgerBefore}\n\n";

echo "Starting parallel requests...\n";
$startTime = microtime(true);

$ch = [];
$mh = curl_multi_init();
$paymentAmount = min(50000, $invoice->grand_total); // Use reasonable amount

for ($i = 0; $i < $parallelRequests; $i++) {
    $ch[$i] = curl_init();
    curl_setopt($ch[$i], CURLOPT_URL, $baseUrl . '/api/payment');
    curl_setopt($ch[$i], CURLOPT_POST, 1);
    curl_setopt($ch[$i], CURLOPT_POSTFIELDS, json_encode([
        'invoice_id' => $invoiceId,
        'amount' => $paymentAmount,
        'payment_type' => 'against_invoice',
        'payment_date' => now()->format('Y-m-d'),
        'mode' => 'bank_transfer',
    ]));
    curl_setopt($ch[$i], CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch[$i], CURLOPT_TIMEOUT, 30);
    curl_multi_add_handle($mh, $ch[$i]);
}

$active = null;
do {
    $status = curl_multi_exec($mh, $active);
    if ($status != CURLM_OK) {
        echo "ERROR: curl_multi_exec failed: " . curl_multi_strerror($status) . "\n";
        break;
    }
} while ($active);

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "Completed in {$duration} seconds\n\n";

// Collect responses
$successCount = 0;
$errorCount = 0;
$httpCodes = [];

foreach ($ch as $c) {
    $response = curl_multi_getcontent($c);
    $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $httpCodes[$httpCode] = ($httpCodes[$httpCode] ?? 0) + 1;
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $successCount++;
    } else {
        $errorCount++;
    }
    
    curl_multi_remove_handle($mh, $c);
    curl_close($c);
}

curl_multi_close($mh);

echo "=== RESULTS ===\n";
echo "Success: {$successCount}\n";
echo "Errors: {$errorCount}\n";
echo "HTTP Codes: " . json_encode($httpCodes) . "\n\n";

// Count ledger entries after test
$ledgerAfter = \App\Models\SupplierTransaction::where('reference_type', 'payment')
    ->where('reference_id', '>', 0)
    ->count();
$newEntries = $ledgerAfter - $ledgerBefore;

echo "=== LEDGER VERIFICATION ===\n";
echo "Payment ledger entries before: {$ledgerBefore}\n";
echo "Payment ledger entries after: {$ledgerAfter}\n";
echo "New entries created: {$newEntries}\n\n";

// Check for duplicates
$duplicates = \Illuminate\Support\Facades\DB::select("
    SELECT reference_type, reference_id, supplier_id, site_id, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM supplier_transactions
    WHERE reference_type = 'payment'
    GROUP BY reference_type, reference_id, supplier_id, site_id
    HAVING count > 1
");

if (empty($duplicates)) {
    echo "✓ NO DUPLICATES FOUND (PASS)\n";
} else {
    echo "✗ DUPLICATES FOUND (FAIL):\n";
    foreach ($duplicates as $dup) {
        echo "  RefID: {$dup->reference_id}, Count: {$dup->count}, IDs: {$dup->ids}\n";
    }
}

// Check current balance
$currentBalance = \App\Models\SupplierTransaction::getCurrentBalance($invoice->supplier_id, $invoice->site_id);
echo "\nCurrent supplier balance: ₹" . number_format($currentBalance, 2) . "\n";

echo "\n=== TEST SUMMARY ===\n";
if ($newEntries <= 1 && empty($duplicates)) {
    echo "✓ STRESS TEST PASSED\n";
    echo "  - No duplicate ledger entries\n";
    echo "  - Concurrency handling working correctly\n";
    exit(0);
} else {
    echo "✗ STRESS TEST FAILED\n";
    echo "  - Expected: 0 or 1 new ledger entry\n";
    echo "  - Actual: {$newEntries} new entries\n";
    exit(1);
}
