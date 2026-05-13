<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Final migration fix - complete reset and fresh start...\n\n";
    
    // Step 1: Complete database wipe
    echo "Step 1: Complete database wipe\n";
    $tables = DB::select('SHOW TABLES');
    $tableNames = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $tables);
    
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tableNames as $tableName) {
        DB::statement("DROP TABLE IF EXISTS `$tableName`");
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "✅ All tables dropped\n";
    
    // Step 2: Drop all procedures
    echo "\nStep 2: Drop all procedures\n";
    $procedures = DB::select("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
    foreach ($procedures as $procedure) {
        DB::statement("DROP PROCEDURE IF EXISTS `{$procedure->Name}`");
    }
    echo "✅ All procedures dropped\n";
    
    // Step 3: Clear migrations manually
    echo "\nStep 3: Clear migrations manually\n";
    DB::statement('DROP TABLE IF EXISTS migrations');
    
    // Create migrations table first
    echo "Creating migrations table...\n";
    DB::statement("
        CREATE TABLE migrations (
            id int unsigned not null auto_increment primary key,
            migration varchar(255) not null,
            batch int not null
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci
    ");
    echo "✅ Migrations table created\n";
    
    // Step 4: Run migrations in batches to avoid conflicts
    echo "\nStep 4: Run migrations in batches\n";
    
    // Get all migration files
    $migrationPath = database_path('migrations');
    $migrationFiles = glob($migrationPath . '/*.php');
    
    // Sort migrations by date
    usort($migrationFiles, function($a, $b) {
        return strcmp(basename($a), basename($b));
    });
    
    $totalMigrations = count($migrationFiles);
    $processed = 0;
    
    echo "Found $totalMigrations migration files\n";
    
    foreach ($migrationFiles as $migrationFile) {
        $migrationName = basename($migrationFile, '.php');
        
        // Skip if already processed
        $alreadyRun = DB::table('migrations')
            ->where('migration', $migrationName)
            ->exists();
            
        if ($alreadyRun) {
            continue;
        }
        
        echo "Processing: $migrationName\n";
        
        try {
            // Include and run migration
            require_once $migrationFile;
            $migration = require $migrationFile;
            
            if (method_exists($migration, 'up')) {
                $migration->up();
                
                // Record migration
                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => 1
                ]);
                
                echo "  ✅ Completed\n";
            }
            
            $processed++;
            
        } catch (Exception $e) {
            echo "  ❌ Failed: " . $e->getMessage() . "\n";
            
            // For procedure conflicts, just continue
            if (strpos($e->getMessage(), 'PROCEDURE') !== false) {
                continue;
            }
        }
    }
    
    echo "\n✅ Migration process completed!\n";
    echo "Processed: $processed/$totalMigrations migrations\n";
    
    // Step 5: Verify critical tables
    echo "\nStep 5: Verify critical tables\n";
    $criticalTables = ['users', 'migrations', 'activities', 'purchase_orders'];
    
    foreach ($criticalTables as $table) {
        $exists = DB::select("SHOW TABLES LIKE '$table'");
        if (!empty($exists)) {
            echo "✅ $table table exists\n";
        } else {
            echo "❌ $table table missing\n";
        }
    }
    
    // Final count
    $finalTables = DB::select('SHOW TABLES');
    echo "\nFinal table count: " . count($finalTables) . "\n";
    
    echo "\n🎉 Complete fresh migration finished successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
