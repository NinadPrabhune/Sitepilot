<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Checking table schemas for missing columns...\n\n";
    
    // Tables to check
    $tablesToCheck = ['purchase_orders', 'activities'];
    
    foreach ($tablesToCheck as $tableName) {
        echo "=== TABLE: $tableName ===\n";
        
        try {
            $columns = DB::select("DESCRIBE $tableName");
            
            foreach ($columns as $column) {
                echo "- {$column->Field} ({$column->Type})\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error checking $tableName: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    // Check migrations to see what columns should exist
    echo "=== CHECKING RELEVANT MIGRATIONS ===\n";
    
    $purchaseOrderMigrations = DB::table('migrations')
            ->where('migration', 'like', '%purchase_order%')
            ->orderBy('migration', 'desc')
            ->get();
    
    echo "Purchase Order migrations:\n";
    foreach ($purchaseOrderMigrations as $migration) {
        echo "- {$migration->migration} (Batch: {$migration->batch})\n";
    }
    
    echo "\n";
    
    $activityMigrations = DB::table('migrations')
            ->where('migration', 'like', '%activity%')
            ->orderBy('migration', 'desc')
            ->get();
    
    echo "Activity migrations:\n";
    foreach ($activityMigrations as $migration) {
        echo "- {$migration->migration} (Batch: {$migration->batch})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
