<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== LEDGER ENTRY ID ANALYSIS ===\n";

// Check current state of both tables
echo "\n--- Daily Progress Reports ledger_entry_id status ---\n";
$dprs = DB::table('daily_progress_reports')->select('id', 'ledger_entry_id', 'machinery_id', 'calculated_amount')->get();
foreach ($dprs as $dpr) {
    echo "DPR ID: {$dpr->id}, Ledger Entry ID: " . ($dpr->ledger_entry_id ?: 'NULL') . ", Amount: {$dpr->calculated_amount}\n";
}

echo "\n--- Daily Consumption Masters ledger_entry_id status ---\n";
$masters = DB::table('daily_consumption_masters')->select('id', 'daily_progress_report_id', 'ledger_entry_id', 'supplier_ledger_entry_id')->get();
foreach ($masters as $master) {
    echo "Master ID: {$master->id}, DPR ID: {$master->daily_progress_report_id}, Ledger Entry ID: " . ($master->ledger_entry_id ?: 'NULL') . ", Supplier Ledger Entry ID: " . ($master->supplier_ledger_entry_id ?: 'NULL') . "\n";
}

// Check machinery_ledger entries to see if they should be linked
echo "\n--- Machinery Ledger Entries Analysis ---\n";
$ledgerEntries = DB::table('machinery_ledger')->select('id', 'reference_type', 'reference_id', 'dpr_id', 'amount', 'description')->get();
foreach ($ledgerEntries as $entry) {
    echo "Ledger ID: {$entry->id}, Reference: {$entry->reference_type} - {$entry->reference_id}, DPR ID: {$entry->dpr_id}, Amount: {$entry->amount}\n";
}

// Check supplier_ledger table structure and entries
echo "\n--- Supplier Ledger Analysis ---\n";
if (Schema::hasTable('supplier_ledger')) {
    $supplierLedgerColumns = Schema::getColumnListing('supplier_ledger');
    echo "Supplier Ledger columns: " . implode(', ', $supplierLedgerColumns) . "\n";
    
    $supplierLedgerEntries = DB::table('supplier_ledger')->limit(3)->get();
    echo "Supplier Ledger entries: " . DB::table('supplier_ledger')->count() . "\n";
    foreach ($supplierLedgerEntries as $entry) {
        echo "  ID: {$entry->id}, Amount: " . ($entry->amount ?: 'N/A') . ", Reference: " . ($entry->reference_type ?: 'N/A') . " - " . ($entry->reference_id ?: 'N/A') . "\n";
    }
} else {
    echo "Supplier ledger table not found\n";
}

// Check controller logic for ledger_entry_id assignment
echo "\n--- Expected Behavior Analysis ---\n";
echo "1. DPR ledger_entry_id: Should link to machinery_ledger entry for DPR credit\n";
echo "2. Consumption Master ledger_entry_id: Should link to machinery_ledger entry for consumption debit\n";
echo "3. Consumption Master supplier_ledger_entry_id: Should link to supplier_ledger for supplier credit\n";
echo "\nCurrent system appears to use reference_type/reference_id instead of direct foreign keys\n";

echo "\n=== ANALYSIS COMPLETE ===\n";
