<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== DATABASE TABLE STRUCTURE ===\n";

// Check daily_consumption_details table structure
echo "\n--- daily_consumption_details table structure ---\n";
$columns = Schema::getColumnListing('daily_consumption_details');
foreach ($columns as $column) {
    echo "Column: {$column}\n";
}

// Check daily_consumption_masters table structure  
echo "\n--- daily_consumption_masters table structure ---\n";
$columns = Schema::getColumnListing('daily_consumption_masters');
foreach ($columns as $column) {
    echo "Column: {$column}\n";
}

// Check if there are any details records
echo "\n--- daily_consumption_details records ---\n";
$details = DB::table('daily_consumption_details')->get();
echo "Total details records: {$details->count()}\n";

foreach ($details as $detail) {
    echo "Detail ID: {$detail->id}\n";
    foreach ($columns as $column) {
        echo "  {$column}: " . ($detail->$column ?? 'NULL') . "\n";
    }
    echo "---\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
