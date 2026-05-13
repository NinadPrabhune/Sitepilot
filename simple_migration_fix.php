<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Simple migration fix approach...\n\n";
    
    // Step 1: Check what tables exist
    $tables = DB::select('SHOW TABLES');
    $existingTables = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $tables);
    
    echo "Current tables: " . count($existingTables) . "\n";
    
    // Step 2: Manually drop problematic tables
    $problematicTables = ['activities_completed'];
    
    foreach ($problematicTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "Dropping problematic table: $table\n";
            DB::statement("DROP TABLE IF EXISTS `$table`");
        }
    }
    
    // Step 3: Remove problematic migration records
    $problematicMigrations = [
        '2026_05_06_131502_create_activities_completed_table'
    ];
    
    foreach ($problematicMigrations as $migration) {
        $deleted = DB::table('migrations')
            ->where('migration', $migration)
            ->delete();
        
        if ($deleted > 0) {
            echo "Removed migration record: $migration\n";
        }
    }
    
    echo "\n✅ Cleanup completed!\n";
    
    // Step 4: Try migration again
    echo "\nRunning migrations...\n";
    
    $output = [];
    $returnCode = 0;
    exec('php artisan migrate --force 2>&1', $output, $returnCode);
    
    echo "Migration output:\n";
    echo implode("\n", array_slice($output, -20)) . "\n"; // Show last 20 lines
    
    if ($returnCode === 0) {
        echo "\n✅ Migrations completed successfully!\n";
        
        // Verify final state
        $finalTables = DB::select('SHOW TABLES');
        echo "Final table count: " . count($finalTables) . "\n";
        
        // Check critical tables
        $criticalTables = ['users', 'migrations', 'activities', 'purchase_orders', 'activities_completed'];
        foreach ($criticalTables as $table) {
            $exists = DB::select("SHOW TABLES LIKE '$table'");
            if (!empty($exists)) {
                echo "✅ $table table exists\n";
            } else {
                echo "❌ $table table missing\n";
            }
        }
        
    } else {
        echo "\n❌ Migration failed with code: $returnCode\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
