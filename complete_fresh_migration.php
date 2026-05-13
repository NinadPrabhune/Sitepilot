<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Running complete fresh migration setup...\n\n";
    
    // Get all remaining tables
    $tables = DB::select('SHOW TABLES');
    $tableNames = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $tables);
    
    echo "Current tables: " . count($tableNames) . "\n";
    
    // Drop all tables again to ensure clean state
    if (!empty($tableNames)) {
        echo "Dropping all remaining tables...\n";
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        foreach ($tableNames as $tableName) {
            DB::statement("DROP TABLE IF EXISTS `$tableName`");
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        echo "✅ All tables dropped\n";
    }
    
    // Clear migrations completely
    echo "Clearing migrations table...\n";
    DB::statement('DROP TABLE IF EXISTS migrations');
    
    // Drop all existing procedures
    echo "Dropping existing procedures...\n";
    $procedures = DB::select("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
    foreach ($procedures as $procedure) {
        DB::statement("DROP PROCEDURE IF EXISTS `{$procedure->Name}`");
    }
    
    echo "Running fresh migrations...\n";
    
    // Run migrate command using shell
    $output = [];
    $returnCode = 0;
    exec('php artisan migrate --force 2>&1', $output, $returnCode);
    
    echo "Migration output:\n";
    echo implode("\n", $output) . "\n";
    
    if ($returnCode === 0) {
        echo "\n✅ All migrations completed successfully!\n";
        
        // Verify final state
        $finalTables = DB::select('SHOW TABLES');
        echo "Final table count: " . count($finalTables) . "\n";
        
        // Check critical tables
        $criticalTables = ['users', 'migrations', 'activities', 'purchase_orders'];
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
