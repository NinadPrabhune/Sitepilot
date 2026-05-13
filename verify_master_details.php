<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Models\Material;
use App\Models\LedgerEntry;

echo "=== CONSUMPTION MASTER DETAILS ===\n";

$master = DailyConsumptionMaster::find(1);
if ($master) {
    echo "Master ID: {$master->id}\n";
    echo "Daily Progress Report ID: '{$master->daily_progress_report_id}'\n";
    echo "Consumption Date: {$master->consumption_date}\n";
    echo "Machinery ID: {$master->machinery_id}\n";
    echo "Consumption Type: {$master->consumption_type}\n";
    echo "Created At: {$master->created_at}\n";
    
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

echo "\n=== VERIFICATION COMPLETE ===\n";
