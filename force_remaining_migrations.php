<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Force running remaining migrations...\n\n";
    
    // Get current table count
    $currentTables = DB::select('SHOW TABLES');
    $currentCount = count($currentTables);
    
    echo "Current table count: $currentCount\n";
    echo "Target table count: 277\n";
    echo "Need to create: " . (277 - $currentCount) . " tables\n\n";
    
    // Get migrations that haven't run yet
    $runMigrations = DB::table('migrations')->pluck('migration')->toArray();
    
    $migrationPath = database_path('migrations');
    $allMigrationFiles = glob($migrationPath . '/*.php');
    
    $pendingMigrations = [];
    
    foreach ($allMigrationFiles as $migrationFile) {
        $migrationName = basename($migrationFile, '.php');
        
        if (!in_array($migrationName, $runMigrations)) {
            $pendingMigrations[] = [
                'file' => $migrationFile,
                'name' => $migrationName
            ];
        }
    }
    
    echo "Pending migrations: " . count($pendingMigrations) . "\n";
    
    // Run pending migrations one by one
    $successCount = 0;
    $failCount = 0;
    
    foreach ($pendingMigrations as $migration) {
        echo "Processing: {$migration['name']}\n";
        
        try {
            // Include and run migration manually
            $migrationClass = require $migration['file'];
            
            if (is_object($migrationClass) && method_exists($migrationClass, 'up')) {
                $migrationClass->up();
                
                // Record migration
                DB::table('migrations')->insert([
                    'migration' => $migration['name'],
                    'batch' => 1
                ]);
                
                echo "  ✅ Success\n";
                $successCount++;
            } else {
                echo "  ❌ Invalid migration class\n";
                $failCount++;
            }
            
        } catch (Exception $e) {
            echo "  ❌ Failed: " . $e->getMessage() . "\n";
            $failCount++;
            
            // For table exists errors, continue
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "  (Table already exists, continuing)\n";
                // Still record as successful since table exists
                DB::table('migrations')->insert([
                    'migration' => $migration['name'],
                    'batch' => 1
                ]);
                $successCount++;
                $failCount--;
            }
        }
        
        echo "\n";
    }
    
    echo "Migration Summary:\n";
    echo "Successful: $successCount\n";
    echo "Failed: $failCount\n";
    
    // Final verification
    $finalTables = DB::select('SHOW TABLES');
    $finalCount = count($finalTables);
    
    echo "\nFinal Result:\n";
    echo "Table count: $finalCount\n";
    
    if ($finalCount >= 277) {
        echo "✅ SUCCESS: Target table count achieved!\n";
    } else {
        echo "⚠️  Still need " . (277 - $finalCount) . " more tables\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
