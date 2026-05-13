<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== UPDATING MACHINERY SITE_ID ===\n";

// Get current machinery records
$machineries = DB::table('machineries')->get();
echo "Current machinery records:\n";
foreach($machineries as $machinery) {
    echo "- ID: " . $machinery->id . ", Name: " . $machinery->name . ", Site ID: " . ($machinery->site_id ?? 'NULL') . PHP_EOL;
}

// Available sites
$sites = [
    1 => 'ECOTEAM OFFICE',
    2 => 'kiloskar', 
    3 => 'Dc link Technologies pvt Ltd new construction',
    4 => 'Abc',
    5 => 'Abc',
    6 => 'Test Site',
    7 => 'Test Project for Machinery Integration'
];

echo "\nAssigning site_id to machinery records:\n";

// Update each machinery record with a different site
$siteAssignments = [
    1 => 7, // owned -> Test Project for Machinery Integration
    2 => 1, // rental monthly -> ECOTEAM OFFICE
    3 => 2, // rental hourly -> kiloskar
    4 => 3, // rental daily -> Dc link Technologies
    5 => 6, // rental NULL -> Test Site
];

foreach($siteAssignments as $machineryId => $siteId) {
    $siteName = $sites[$siteId] ?? 'Unknown Site';
    
    $updated = DB::table('machineries')
        ->where('id', $machineryId)
        ->update(['site_id' => $siteId, 'updated_at' => now()]);
    
    if ($updated) {
        echo "- Machinery ID " . $machineryId . " -> Site ID " . $siteId . " (" . $siteName . ")" . PHP_EOL;
    } else {
        echo "- Failed to update Machinery ID " . $machineryId . PHP_EOL;
    }
}

echo "\n=== VERIFICATION ===\n";
$updatedMachineries = DB::table('machineries')
    ->select('id', 'name', 'site_id', 'owned_by', 'rate_type', 'status')
    ->get();

echo "Updated machinery records:\n";
foreach($updatedMachineries as $machinery) {
    $siteName = $sites[$machinery->site_id] ?? 'Unknown Site';
    echo "- ID: " . $machinery->id . ", Name: " . $machinery->name . 
         ", Site ID: " . $machinery->site_id . " (" . $siteName . ")" .
         " [" . $machinery->owned_by . ", " . ($machinery->rate_type ?? 'NULL') . ", " . $machinery->status . "]" . PHP_EOL;
}

echo "\n✅ Site_id update completed!" . PHP_EOL;
