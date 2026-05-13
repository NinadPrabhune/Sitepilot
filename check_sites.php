<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING AVAILABLE SITES ===\n";

// Check projects table (sites)
$projects = DB::table('projects')->select('id', 'name')->get();
echo "Available Projects (Sites):\n";
foreach($projects as $project) {
    echo "- ID: " . $project->id . ", Name: " . $project->name . PHP_EOL;
}

if ($projects->isEmpty()) {
    echo "No projects found. Checking if projects table exists...\n";
    $tables = DB::select("SHOW TABLES LIKE '%project%'");
    foreach($tables as $table) {
        foreach($table as $value) {
            echo "- Table: " . $value . PHP_EOL;
        }
    }
}

echo "\n=== CURRENT MACHINERY RECORDS ===\n";
$machineries = DB::table('machineries')->select('id', 'name', 'site_id', 'owned_by', 'rate_type', 'status')->get();
foreach($machineries as $machinery) {
    echo "- ID: " . $machinery->id . ", Name: " . $machinery->name . ", Site ID: " . ($machinery->site_id ?? 'NULL') . 
         " (" . $machinery->owned_by . ", " . ($machinery->rate_type ?? 'NULL') . ", " . $machinery->status . ")" . PHP_EOL;
}
