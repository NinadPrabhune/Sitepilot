<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Finding missing tables...\n\n";
    
    // Get current tables
    $currentTables = DB::select('SHOW TABLES');
    $currentTableNames = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $currentTables);
    
    // Get all migration files to find expected tables
    $migrationPath = database_path('migrations');
    $migrationFiles = glob($migrationPath . '/*.php');
    
    $expectedTables = [];
    
    foreach ($migrationFiles as $migrationFile) {
        $content = file_get_contents($migrationFile);
        
        // Look for Schema::create calls
        if (preg_match_all('/Schema::create\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $expectedTables[] = $matches[1];
        }
        
        // Look for create table patterns
        if (preg_match_all('/create table `([^`]+)`/i', $content, $matches)) {
            $expectedTables[] = $matches[1];
        }
    }
    
    $expectedTables = array_unique($expectedTables);
    
    echo "Current tables: " . count($currentTableNames) . "\n";
    echo "Expected tables from migrations: " . count($expectedTables) . "\n\n";
    
    // Find missing tables
    $missingTables = array_diff($expectedTables, $currentTableNames);
    
    if (!empty($missingTables)) {
        echo "❌ Missing tables:\n";
        foreach ($missingTables as $table) {
            echo "- $table\n";
        }
        
        echo "\nAttempting to create missing tables...\n";
        
        // Try to run specific migrations for missing tables
        foreach ($missingTables as $missingTable) {
            echo "\nTrying to create: $missingTable\n";
            
            // Find the migration file that creates this table
            $targetMigration = null;
            foreach ($migrationFiles as $migrationFile) {
                $content = file_get_contents($migrationFile);
                if (strpos($content, "'$missingTable'") !== false || 
                    strpos($content, "\"$missingTable\"") !== false ||
                    strpos($content, "`$missingTable`") !== false) {
                    
                    $migrationName = basename($migrationFile, '.php');
                    echo "Found in migration: $migrationName\n";
                    
                    // Try to run this specific migration
                    $output = [];
                    $returnCode = 0;
                    exec("php artisan migrate --path=$migrationFile --force 2>&1", $output, $returnCode);
                    
                    if ($returnCode === 0) {
                        echo "✅ Successfully created $missingTable\n";
                    } else {
                        echo "❌ Failed to create $missingTable\n";
                        echo "Error: " . implode("\n", array_slice($output, -5)) . "\n";
                    }
                    break;
                }
            }
        }
        
    } else {
        echo "✅ All expected tables are present\n";
    }
    
    // Final count check
    $finalTables = DB::select('SHOW TABLES');
    echo "\nFinal table count: " . count($finalTables) . "\n";
    
    // Check if we reached the expected 277
    if (count($finalTables) >= 277) {
        echo "✅ Target table count achieved (>= 277)\n";
    } else {
        echo "⚠️  Still below target (need 277, have " . count($finalTables) . ")\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
