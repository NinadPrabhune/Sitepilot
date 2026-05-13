<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Models\Material;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

echo "=== CORRECTED FUEL CONSUMPTION VERIFICATION ===\n";

// Check Consumption Master
$master = DailyConsumptionMaster::find(1);
if ($master) {
    echo "Consumption Master ID: {$master->id}\n";
    echo "Daily Progress Report ID: '{$master->daily_progress_report_id}'\n";
    echo "Consumption Date: {$master->consumption_date}\n";
    echo "Machinery ID: {$master->machinery_id}\n";
    echo "Consumption Type: {$master->consumption_type}\n";
    echo "Ledger Entry ID: {$master->ledger_entry_id}\n";
    
    // Check details using correct column name
    $details = DailyConsumptionDetails::where('daily_consumption_master_id', $master->id)->get();
    echo "Details Count: {$details->count()}\n";
    
    foreach ($details as $detail) {
        $material = Material::find($detail->material_id);
        echo "  - Detail ID: {$detail->id}, Material: {$material->name}, Quantity: {$detail->quantity} {$detail->unit}\n";
        echo "    Unit Price: {$detail->unit_price}, Total Price: {$detail->total_price}\n";
        echo "    Remarks: {$detail->remarks}\n";
    }
    
    // Check ledger entries
    $ledger = LedgerEntry::where('reference_type', 'DailyConsumptionMaster')
                        ->where('reference_id', $master->id)
                        ->first();
    if ($ledger) {
        echo "Ledger ID: {$ledger->id}, Amount: {$ledger->amount}\n";
        echo "Ledger Type: {$ledger->ledger_type}\n";
    } else {
        echo "No Ledger Entry found\n";
    }
} else {
    echo "No Consumption Master found\n";
}

// Check DPR relationship
echo "\n--- DPR Relationship Check ---\n";
$dpr = DB::table('daily_progress_reports')->find(1);
if ($dpr) {
    echo "DPR ID: {$dpr->id}, Date: {$dpr->date}\n";
    
    $relatedMaster = DailyConsumptionMaster::where('daily_progress_report_id', 1)->first();
    if ($relatedMaster) {
        echo "Found related Consumption Master: {$relatedMaster->id}\n";
    } else {
        echo "No related Consumption Master found for DPR ID 1\n";
    }
}

echo "\n=== VERIFICATION COMPLETE ===\n";
