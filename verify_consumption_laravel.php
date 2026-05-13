<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Models\Material;
use App\Models\LedgerEntry;

echo "=== FUEL CONSUMPTION RECORD VERIFICATION ===\n";

$dpr = DailyProgressReport::find(1);
if ($dpr) {
    echo "DPR ID: {$dpr->id}\n";
    echo "Date: {$dpr->date}\n";
    
    $master = DailyConsumptionMaster::where('daily_progress_report_id', 1)->first();
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
} else {
    echo "DPR not found\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
