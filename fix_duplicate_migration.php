<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Fixing duplicate migration issue...\n";
    
    // Check if activities_completed table exists
    $tableExists = DB::select("SHOW TABLES LIKE 'activities_completed'");
    
    if (!empty($tableExists)) {
        echo "Dropping existing activities_completed table...\n";
        DB::statement("DROP TABLE activities_completed");
        echo "✅ Dropped activities_completed table\n";
    }
    
    // Remove the migration from migrations table
    $migrationName = '2026_05_06_131502_create_activities_completed_table';
    $deleted = DB::table('migrations')
        ->where('migration', $migrationName)
        ->delete();
    
    if ($deleted > 0) {
        echo "✅ Removed migration record: $migrationName\n";
    }
    
    echo "\n✅ Migration fix completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
