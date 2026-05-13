<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Simple table count analysis...\n\n";
    
    // Current table count
    $tables = DB::select('SHOW TABLES');
    $currentCount = count($tables);
    
    echo "Current table count: $currentCount\n";
    echo "Expected table count: 277\n";
    echo "Difference: " . (277 - $currentCount) . "\n\n";
    
    if ($currentCount < 277) {
        echo "❌ Missing " . (277 - $currentCount) . " tables\n";
        
        // Check migrations that failed
        $failedMigrations = DB::table('migrations')
            ->where('migration', 'like', '%2026%')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();
        
        echo "\nLast 10 migrations attempted:\n";
        foreach ($failedMigrations as $migration) {
            echo "- {$migration->migration}\n";
        }
        
        // Try to run remaining migrations
        echo "\nAttempting to run remaining migrations...\n";
        
        $output = [];
        $returnCode = 0;
        exec('php artisan migrate --force 2>&1', $output, $returnCode);
        
        echo "Migration output (last 10 lines):\n";
        echo implode("\n", array_slice($output, -10)) . "\n";
        
        if ($returnCode === 0) {
            echo "✅ Additional migrations completed\n";
        } else {
            echo "❌ Additional migrations failed\n";
        }
        
    } else {
        echo "✅ Table count is correct\n";
    }
    
    // Final verification
    $finalTables = DB::select('SHOW TABLES');
    $finalCount = count($finalTables);
    
    echo "\nFinal verification:\n";
    echo "Table count: $finalCount\n";
    
    if ($finalCount >= 277) {
        echo "✅ SUCCESS: Target table count achieved\n";
    } else {
        echo "⚠️  Still need " . (277 - $finalCount) . " more tables\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
