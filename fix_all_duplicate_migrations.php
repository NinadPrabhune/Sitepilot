<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Fixing all duplicate migration issues...\n";
    
    // List of problematic migrations that create tables already existing
    $problematicMigrations = [
        '2026_05_06_131502_create_activities_table',
        '2026_05_06_131502_create_purchase_orders_table'
    ];
    
    foreach ($problematicMigrations as $migrationName) {
        echo "Processing migration: $migrationName\n";
        
        // Extract table name from migration
        $tableName = str_replace(['2026_05_06_131502_create_', '_table'], '', $migrationName);
        
        echo "Checking table: $tableName\n";
        
        // Check if table exists
        $tableExists = DB::select("SHOW TABLES LIKE '$tableName'");
        
        if (!empty($tableExists)) {
            echo "Dropping existing table: $tableName\n";
            DB::statement("DROP TABLE `$tableName`");
            echo "✅ Dropped table: $tableName\n";
        }
        
        // Remove migration record
        $deleted = DB::table('migrations')
            ->where('migration', $migrationName)
            ->delete();
        
        if ($deleted > 0) {
            echo "✅ Removed migration record: $migrationName\n";
        }
        
        echo "\n";
    }
    
    echo "✅ All duplicate migration fixes completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
