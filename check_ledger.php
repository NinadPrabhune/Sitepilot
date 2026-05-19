<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking ledger entries:\n";

$entries = DB::table('machinery_ledger')->get(['id', 'amount', 'reference_type', 'reference_id']);

foreach ($entries as $entry) {
    echo "ID: {$entry->id}, Amount: {$entry->amount}, Type: {$entry->reference_type}, Ref: {$entry->reference_id}\n";
}

echo "\nDPR ledger_entry_id: ";
$dpr = DB::table('daily_progress_reports')->where('id', 5)->first();
echo $dpr ? $dpr->ledger_entry_id : 'Not found';

echo "\nConsumptionMaster ledger_entry_id: ";
$master = DB::table('daily_consumption_masters')->where('id', 1)->first();
echo $master ? $master->ledger_entry_id : 'Not found';
