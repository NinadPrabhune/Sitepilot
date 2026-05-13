<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Ultimate migration fix - clean slate approach...\n\n";
    
    // Step 1: Complete database wipe
    echo "Step 1: Complete database wipe\n";
    $tables = DB::select('SHOW TABLES');
    $tableNames = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $tables);
    
    if (!empty($tableNames)) {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tableNames as $tableName) {
            DB::statement("DROP TABLE IF EXISTS `$tableName`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        echo "✅ All tables dropped\n";
    }
    
    // Step 2: Drop all procedures and functions
    echo "\nStep 2: Drop all procedures and functions\n";
    $procedures = DB::select("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
    foreach ($procedures as $procedure) {
        DB::statement("DROP PROCEDURE IF EXISTS `{$procedure->Name}`");
    }
    
    $functions = DB::select("SHOW FUNCTION STATUS WHERE Db = DATABASE()");
    foreach ($functions as $function) {
        DB::statement("DROP FUNCTION IF EXISTS `{$function->Name}`");
    }
    echo "✅ All procedures and functions dropped\n";
    
    // Step 3: Create fresh migrations table
    echo "\nStep 3: Create fresh migrations table\n";
    DB::statement("
        CREATE TABLE migrations (
            id int unsigned not null auto_increment primary key,
            migration varchar(255) not null,
            batch int not null
        ) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci
    ");
    echo "✅ Migrations table created\n";
    
    // Step 4: Use Laravel's migrate command with fresh flag
    echo "\nStep 4: Running fresh migrate command\n";
    
    // First, run migrate:fresh to get clean state
    $output = [];
    $returnCode = 0;
    exec('php artisan migrate:fresh --force 2>&1', $output, $returnCode);
    
    echo "Fresh migrate output (last 30 lines):\n";
    echo implode("\n", array_slice($output, -30)) . "\n";
    
    if ($returnCode === 0) {
        echo "\n✅ Fresh migrations completed successfully!\n";
        
        // Step 5: Verify final state
        echo "\nStep 5: Verify final state\n";
        
        $finalTables = DB::select('SHOW TABLES');
        echo "Final table count: " . count($finalTables) . "\n";
        
        // Check critical tables
        $criticalTables = [
            'users', 'migrations', 'activities', 'purchase_orders', 
            'activities_completed', 'work_spaces', 'settings'
        ];
        
        echo "\nCritical tables status:\n";
        foreach ($criticalTables as $table) {
            $exists = DB::select("SHOW TABLES LIKE '$table'");
            if (!empty($exists)) {
                $count = DB::table($table)->count();
                echo "✅ $table table exists ($count records)\n";
            } else {
                echo "❌ $table table missing\n";
            }
        }
        
        // Check migration count
        $migrationCount = DB::table('migrations')->count();
        echo "\nMigrations run: $migrationCount\n";
        
        if ($migrationCount > 0) {
            echo "\n🎉 DATABASE SETUP COMPLETED SUCCESSFULLY!\n";
            echo "Ready for seeding and application use.\n";
        } else {
            echo "\n⚠️  Warning: No migrations were run\n";
        }
        
    } else {
        echo "\n❌ Fresh migrate failed with code: $returnCode\n";
        
        // Try alternative approach
        echo "\nTrying alternative approach...\n";
        
        // Run individual migrations for critical tables first
        $criticalMigrations = [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_074112_create_activities_table.php',
            'database/migrations/2026_02_24_000003_create_purchase_orders_table.php'
        ];
        
        foreach ($criticalMigrations as $migrationFile) {
            if (file_exists($migrationFile)) {
                echo "Running: " . basename($migrationFile) . "\n";
                $output = [];
                exec("php artisan migrate --path=$migrationFile --force 2>&1", $output, $code);
                
                if ($code === 0) {
                    echo "  ✅ Success\n";
                } else {
                    echo "  ❌ Failed: " . implode("\n    ", array_slice($output, -5)) . "\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
