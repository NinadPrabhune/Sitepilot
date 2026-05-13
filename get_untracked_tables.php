<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== UNTRACKED TABLES ANALYSIS ===\n\n";

// Get all database tables
$dbTables = \DB::select('SHOW TABLES');
$tableNames = array_map(function($table) {
    $tableArray = (array)$table;
    return array_values($tableArray)[0];
}, $dbTables);

// Get migration files and extract table names
$migrationPath = database_path('migrations');
$migrationFiles = glob($migrationPath . '/*.php');
$tablesFromMigrations = [];

foreach ($migrationFiles as $file) {
    $content = file_get_contents($file);
    $migrationName = basename($file, '.php');
    
    // Look for Schema::create calls
    if (preg_match_all('/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[1] as $table) {
            $tablesFromMigrations[$table] = $migrationName;
        }
    }
    
    // Look for Schema::rename calls
    if (preg_match_all('/Schema::rename\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        foreach ($matches[2] as $newTable) {
            $tablesFromMigrations[$newTable] = $migrationName . ' (renamed)';
        }
    }
}

// Find tables that exist but are not tracked by migrations
$untrackedTables = array_diff($tableNames, array_keys($tablesFromMigrations));

echo "TABLES IN DATABASE BUT NOT TRACKED BY MIGRATIONS:\n";
if (empty($untrackedTables)) {
    echo "  None - All tables are tracked by migrations\n";
} else {
    foreach ($untrackedTables as $table) {
        echo "  - $table\n";
    }
}

echo "\nTotal untracked tables: " . count($untrackedTables) . "\n";
