<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== LEDGER ENTRY ID RELATIONSHIP ANALYSIS ===\n";

// Check daily_progress_reports table structure
echo "\n--- daily_progress_reports table structure ---\n";
$columns = Schema::getColumnListing('daily_progress_reports');
foreach ($columns as $column) {
    if (strpos($column, 'ledger') !== false) {
        echo "Ledger-related column: {$column}\n";
    }
}

// Check for ledger-related tables
echo "\n--- Looking for ledger tables ---\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $table) {
    $tableName = array_values((array)$table)[0];
    if (strpos(strtolower($tableName), 'ledger') !== false) {
        echo "Found ledger table: {$tableName}\n";
        
        // Check if this table has an 'id' column
        if (Schema::hasColumn($tableName, 'id')) {
            echo "  - Has 'id' column\n";
        }
    }
}

// Check DailyProgressReport model relationships
echo "\n--- Checking DailyProgressReport model ---\n";
$modelPath = app_path('Models/DailyProgressReport.php');
if (file_exists($modelPath)) {
    $modelContent = file_get_contents($modelPath);
    
    // Look for ledger relationships
    if (strpos($modelContent, 'ledger') !== false) {
        echo "Model contains ledger-related code\n";
        
        // Extract relationship methods
        preg_match_all('/function\s+(\w*ledger\w*)\s*\(/', $modelContent, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $method) {
                echo "  - Found relationship method: {$method}\n";
            }
        }
    }
}

// Check current DPR record for ledger_entry_id
echo "\n--- Current DPR ledger_entry_id values ---\n";
$dprs = DB::table('daily_progress_reports')->select('id', 'ledger_entry_id')->get();
foreach ($dprs as $dpr) {
    echo "DPR ID: {$dpr->id}, Ledger Entry ID: " . ($dpr->ledger_entry_id ?? 'NULL') . "\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";
