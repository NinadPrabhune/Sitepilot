<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Checking current table count vs expected...\n\n";
    
    // Get current table count
    $tables = DB::select('SHOW TABLES');
    $currentCount = count($tables);
    
    echo "Current table count: $currentCount\n";
    
    // Check migrations count
    $migrationCount = DB::table('migrations')->count();
    echo "Migrations run: $migrationCount\n";
    
    // Get all migration files
    $migrationPath = database_path('migrations');
    $migrationFiles = glob($migrationPath . '/*.php');
    $totalMigrationFiles = count($migrationFiles);
    
    echo "Total migration files: $totalMigrationFiles\n";
    echo "Expected table count (approx): $totalMigrationFiles\n\n";
    
    // List all current tables
    echo "Current tables:\n";
    $tableNames = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $tables);
    
    sort($tableNames);
    
    foreach ($tableNames as $tableName) {
        echo "- $tableName\n";
    }
    
    echo "\nAnalysis:\n";
    if ($currentCount < 277) {
        echo "❌ Missing tables: " . (277 - $currentCount) . "\n";
        echo "Some migrations may have failed or tables were dropped\n";
    } elseif ($currentCount > 277) {
        echo "⚠️  Extra tables: " . ($currentCount - 277) . "\n";
        echo "More tables than expected\n";
    } else {
        echo "✅ Table count matches expected 277\n";
    }
    
    // Check for any failed migrations
    echo "\nChecking for any failed migration states...\n";
    
    // Check if there are any migration-related issues
    try {
        $failedMigrations = DB::table('migrations')
            ->where('migration', 'like', '%failed%')
            ->orWhere('migration', 'like', '%error%')
            ->count();
            
        if ($failedMigrations > 0) {
            echo "Found $failedMigrations potentially failed migrations\n";
        } else {
            echo "No obviously failed migrations found\n";
        }
    } catch (Exception $e) {
        echo "Could not check for failed migrations: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
