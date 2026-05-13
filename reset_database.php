<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Resetting database completely...\n\n";
    
    // Get all table names
    $tables = DB::select('SHOW TABLES');
    $tableNames = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $tables);
    
    echo "Found " . count($tableNames) . " tables\n";
    
    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    // Drop all tables
    foreach ($tableNames as $tableName) {
        echo "Dropping table: $tableName\n";
        DB::statement("DROP TABLE IF EXISTS `$tableName`");
    }
    
    // Re-enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    
    echo "\n✅ All tables dropped successfully!\n";
    
    // Clear migrations table
    echo "Clearing migrations table...\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDatabase reset complete. Ready for fresh migrations.\n";
