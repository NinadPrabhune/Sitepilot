<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MACHINERY TABLE STRUCTURE ===\n";
$schema = DB::select('DESCRIBE machineries');
foreach($schema as $col) {
    echo $col->Field . ' (' . $col->Type . ')' . PHP_EOL;
}

echo "\n=== CURRENT MACHINERY DATA COMBINATIONS ===\n";
$combinations = DB::table('machineries')
    ->select('owned_by', 'rate_type', 'status', DB::raw('COUNT(*) as count'))
    ->groupBy('owned_by', 'rate_type', 'status')
    ->orderBy('owned_by')
    ->orderBy('rate_type')
    ->orderBy('status')
    ->get();

foreach($combinations as $comb) {
    echo "Ownership: " . ($comb->owned_by ?? 'NULL') . 
         ", Rate Type: " . ($comb->rate_type ?? 'NULL') . 
         ", Status: " . ($comb->status ?? 'NULL') . 
         " (Count: " . $comb->count . ")" . PHP_EOL;
}

echo "\n=== TOTAL RECORDS ===\n";
$total = DB::table('machineries')->count();
echo "Total machinery records: " . $total . PHP_EOL;

echo "\n=== UNIQUE OWNERSHIP VALUES ===\n";
$ownerships = DB::table('machineries')->distinct()->pluck('owned_by');
foreach($ownerships as $ownership) {
    echo "- " . ($ownership ?? 'NULL') . PHP_EOL;
}

echo "\n=== UNIQUE RATE TYPE VALUES ===\n";
$rateTypes = DB::table('machineries')->distinct()->pluck('rate_type');
foreach($rateTypes as $rateType) {
    echo "- " . ($rateType ?? 'NULL') . PHP_EOL;
}

echo "\n=== UNIQUE STATUS VALUES ===\n";
$statuses = DB::table('machineries')->distinct()->pluck('status');
foreach($statuses as $status) {
    echo "- " . ($status ?? 'NULL') . PHP_EOL;
}
