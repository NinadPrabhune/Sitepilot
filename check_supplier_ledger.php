<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\SupplierTransaction;

echo "Checking supplier 'Ninad'...\n";

// Find supplier
$supplier = Supplier::where('name', 'like', '%Ninad%')->first();

if (!$supplier) {
    echo "Supplier 'Ninad' not found.\n";
    exit(1);
}

echo "Found supplier: ID {$supplier->id}, Name: {$supplier->name}\n\n";

// Check POs for this supplier
$pos = PurchaseOrder::where('supplier_id', $supplier->id)->get();
echo "POs for this supplier: " . $pos->count() . "\n";
foreach ($pos as $po) {
    echo "  - PO #{$po->po_number}, ID: {$po->id}, Amount: ₹{$po->grand_total}, Status: {$po->status}\n";
}

// Check ledger entries for this supplier
$ledgerEntries = SupplierTransaction::where('supplier_id', $supplier->id)->get();
echo "\nLedger entries for this supplier: " . $ledgerEntries->count() . "\n";
foreach ($ledgerEntries as $entry) {
    echo "  - ID: {$entry->id}, Type: {$entry->reference_type}, RefID: {$entry->reference_id}, Debit: {$entry->debit}, Credit: {$entry->credit}, Balance: {$entry->balance}\n";
}

// Check if there are any invoices for this supplier
$invoices = DB::table('purchase_invoices')->where('supplier_id', $supplier->id)->get();
echo "\nInvoices for this supplier: " . $invoices->count() . "\n";
foreach ($invoices as $inv) {
    echo "  - Invoice #{$inv->invoice_number}, ID: {$inv->id}, Amount: ₹{$inv->grand_total}, Status: {$inv->status}\n";
}
