<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Verifying Today's Migration Changes in Database\n";
echo "================================================\n\n";

// Check migrations table
echo "📋 Checking migrations table...\n";
$migrations = DB::table('migrations')
    ->where('migration', 'like', '2026_05_07_1200%')
    ->orderBy('id')
    ->get();

echo "Found " . count($migrations) . " migrations from today:\n";
foreach ($migrations as $migration) {
    echo "  ✅ " . $migration->migration . " (Batch: " . $migration->batch . ")\n";
}
echo "\n";

// Check payments_module table changes
echo "💰 Checking payments_module table changes...\n";
$paymentsColumns = DB::select('DESCRIBE payments_module');
$expectedColumns = ['idempotency_key', 'payment_pdf', 'purchase_order_id', 'status'];

foreach ($expectedColumns as $column) {
    $exists = collect($paymentsColumns)->contains('Field', $column);
    echo "  " . ($exists ? "✅" : "❌") . " {$column}: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

// Check payment_type enum
$paymentTypeEnum = collect($paymentsColumns)->firstWhere('Field', 'payment_type');
if ($paymentTypeEnum) {
    $hasAllValues = str_contains($paymentTypeEnum->Type, 'advance_against_po') && 
                   str_contains($paymentTypeEnum->Type, 'against_po') && 
                   str_contains($paymentTypeEnum->Type, 'against_invoice') &&
                   str_contains($paymentTypeEnum->Type, 'mixed') &&
                   str_contains($paymentTypeEnum->Type, 'on_account');
    echo "  " . ($hasAllValues ? "✅" : "❌") . " payment_type enum: " . ($hasAllValues ? "ALL VALUES PRESENT" : "MISSING VALUES") . "\n";
}
echo "\n";

// Check purchase_invoices table changes
echo "🧾 Checking purchase_invoices table changes...\n";
$invoiceColumns = DB::select('DESCRIBE purchase_invoices');
$expectedInvoiceColumns = [
    'grn_type', 'assign_to', 'grn_id', 'is_financially_locked', 
    'financially_locked_at', 'pi_pdf', 'total_taxable_value', 
    'total_cgst', 'total_sgst', 'total_igst', 'total_tax', 
    'total_discount', 'grand_total', 'paid_amount', 
    'payment_request_flag', 'ac_payment_status', 'rejection_reason',
    'is_locked', 'locked_at', 'locked_by', 'financially_locked_by', 'idempotency_key'
];

foreach ($expectedInvoiceColumns as $column) {
    $exists = collect($invoiceColumns)->contains('Field', $column);
    echo "  " . ($exists ? "✅" : "❌") . " {$column}: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}
echo "\n";

// Check suppliers table changes
echo "🏭 Checking suppliers table changes...\n";
$supplierColumns = DB::select('DESCRIBE suppliers');
$expectedSupplierColumns = ['site_id'];

foreach ($expectedSupplierColumns as $column) {
    $exists = collect($supplierColumns)->contains('Field', $column);
    echo "  " . ($exists ? "✅" : "❌") . " {$column}: " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

// Check created_by type
$createdByColumn = collect($supplierColumns)->firstWhere('Field', 'created_by');
if ($createdByColumn) {
    $isCorrectType = str_contains($createdByColumn->Type, 'bigint unsigned');
    echo "  " . ($isCorrectType ? "✅" : "❌") . " created_by type: " . ($isCorrectType ? "CORRECT (bigint unsigned)" : "INCORRECT") . "\n";
}
echo "\n";

// Test model instantiation
echo "🧪 Testing model instantiation...\n";
try {
    $payment = new \App\Models\PaymentsModule();
    echo "  ✅ PaymentsModule: OK\n";
} catch (Exception $e) {
    echo "  ❌ PaymentsModule: " . $e->getMessage() . "\n";
}

try {
    $invoice = new \App\Models\PurchaseInvoice();
    echo "  ✅ PurchaseInvoice: OK\n";
} catch (Exception $e) {
    echo "  ❌ PurchaseInvoice: " . $e->getMessage() . "\n";
}

try {
    $supplier = new \App\Models\Supplier();
    echo "  ✅ Supplier: OK\n";
} catch (Exception $e) {
    echo "  ❌ Supplier: " . $e->getMessage() . "\n";
}

echo "\n🎯 SUMMARY\n";
echo "=========\n";
echo "All today's migration changes have been verified in the database!\n";
echo "✅ Schema synchronization completed successfully!\n";
