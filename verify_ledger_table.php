<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== LEDGER TABLE VERIFICATION ===\n";

// Check machinery_ledger table structure
echo "\n--- machinery_ledger table structure ---\n";
$columns = Schema::getColumnListing('machinery_ledger');
foreach ($columns as $column) {
    echo "Column: {$column}\n";
}

// Check if there are any records in machinery_ledger
echo "\n--- machinery_ledger records ---\n";
$records = DB::table('machinery_ledger')->limit(3)->get();
echo "Total records: " . DB::table('machinery_ledger')->count() . "\n";

foreach ($records as $record) {
    echo "ID: {$record->id}, Reference: {$record->reference_type} - {$record->reference_id}, Amount: {$record->amount}\n";
}

// Check if any DPR records have ledger_entry_id populated
echo "\n--- DPR records with ledger_entry_id ---\n";
$dprs = DB::table('daily_progress_reports')->whereNotNull('ledger_entry_id')->get();
echo "DPRs with ledger_entry_id: {$dprs->count()}\n";

foreach ($dprs as $dpr) {
    echo "DPR ID: {$dpr->id}, Ledger Entry ID: {$dpr->ledger_entry_id}\n";
    
    // Check if the ledger entry exists
    $ledger = DB::table('machinery_ledger')->where('id', $dpr->ledger_entry_id)->first();
    if ($ledger) {
        echo "  -> Ledger found: Amount {$ledger->amount}, Reference {$ledger->reference_type}\n";
    } else {
        echo "  -> Ledger NOT found!\n";
    }
}

echo "\n=== VERIFICATION COMPLETE ===\n";
