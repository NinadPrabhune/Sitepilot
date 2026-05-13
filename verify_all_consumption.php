<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Models\Material;
use App\Models\LedgerEntry;

echo "=== ALL FUEL CONSUMPTION RECORDS VERIFICATION ===\n";

// Check all DPRs
$dprs = DailyProgressReport::all();
echo "Total DPRs: {$dprs->count()}\n";

foreach ($dprs as $dpr) {
    echo "\n--- DPR ID: {$dpr->id}, Date: {$dpr->date} ---\n";
    
    $master = DailyConsumptionMaster::where('daily_progress_report_id', $dpr->id)->first();
    if ($master) {
        echo "Consumption Master ID: {$master->id}\n";
        
        $details = DailyConsumptionDetails::where('consumption_master_id', $master->id)->get();
        echo "Details Count: {$details->count()}\n";
        
        foreach ($details as $detail) {
            $material = Material::find($detail->material_id);
            echo "  - Detail ID: {$detail->id}, Material: {$material->name}, Quantity: {$detail->quantity} {$detail->unit}\n";
        }
        
        $ledger = LedgerEntry::where('reference_type', 'DailyConsumptionMaster')
                            ->where('reference_id', $master->id)
                            ->first();
        if ($ledger) {
            echo "Ledger ID: {$ledger->id}, Amount: {$ledger->amount}\n";
        } else {
            echo "No Ledger Entry found\n";
        }
    } else {
        echo "No Consumption Master found\n";
    }
}

echo "\n=== ALL CONSUMPTION MASTERS ===\n";
$masters = DailyConsumptionMaster::all();
echo "Total Consumption Masters: {$masters->count()}\n";

foreach ($masters as $master) {
    echo "Master ID: {$master->id}, DPR ID: {$master->daily_progress_report_id}, Date: {$master->consumption_date}\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
