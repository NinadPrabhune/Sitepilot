<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Simple database comparison...\n\n";
    
    // Current database
    echo "=== CURRENT DATABASE (sitepilot_local) ===\n";
    $currentTables = DB::select('SHOW TABLES');
    $currentCount = count($currentTables);
    
    echo "Table count: $currentCount\n";
    
    // Check backup database exists
    $backupDb = 'sitepilot_local_bk';
    $backupExists = false;
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=$backupDb", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $backupTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $backupCount = count($backupTables);
        $backupExists = true;
        $pdo = null;
        
        echo "\n=== BACKUP DATABASE ($backupDb) ===\n";
        echo "Table count: $backupCount\n";
        
    } catch (Exception $e) {
        echo "\n=== BACKUP DATABASE ===\n";
        echo "❌ Could not access backup database: " . $e->getMessage() . "\n";
    }
    
    // Compare critical tables
    echo "\n=== CRITICAL TABLES COMPARISON ===\n";
    $criticalTables = ['users', 'activities', 'purchase_orders', 'work_spaces', 'settings'];
    
    foreach ($criticalTables as $table) {
        $currentExists = false;
        $backupExists = false;
        
        // Check current
        foreach ($currentTables as $currentTable) {
            $tableName = array_values((array)$currentTable)[0];
            if ($tableName === $table) {
                $currentExists = true;
                break;
            }
        }
        
        // Check backup
        if ($backupExists) {
            foreach ($backupTables as $backupTable) {
                if ($backupTable === $table) {
                    $backupExists = true;
                    break;
                }
            }
        }
        
        if ($currentExists && $backupExists) {
            echo "✅ $table: Both databases\n";
        } elseif ($currentExists && !$backupExists) {
            echo "➕ $table: Only in current database\n";
        } elseif (!$currentExists && $backupExists) {
            echo "➖ $table: Only in backup database\n";
        } else {
            echo "❌ $table: Missing from both\n";
        }
    }
    
    // Summary
    echo "\n=== SUMMARY ===\n";
    echo "Current DB tables: $currentCount\n";
    echo "Expected from old: 277\n";
    echo "Backup accessible: " . ($backupExists ? "Yes" : "No") . "\n";
    
    if ($currentCount >= 277) {
        echo "✅ Current database has expected table count\n";
    } else {
        echo "⚠️  Current database needs " . (277 - $currentCount) . " more tables\n";
    }
    
    // Check for column mismatches in current DB
    echo "\n=== COLUMN ANALYSIS FOR CURRENT DB ===\n";
    
    foreach ($criticalTables as $table) {
        try {
            $columns = DB::select("DESCRIBE $table");
            $columnCount = count($columns);
            
            echo "$table: $columnCount columns\n";
            
            // Check for obvious missing columns
            switch ($table) {
                case 'users':
                    $userColumns = ['id', 'name', 'email', 'password', 'type', 'active_status', 'workspace_id', 'created_by', 'created_at', 'updated_at'];
                    $actualColumns = array_map(function($col) { return $col->Field; }, $columns);
                    $missing = array_diff($userColumns, $actualColumns);
                    if (!empty($missing)) {
                        echo "  ❌ Missing basic columns: " . implode(', ', $missing) . "\n";
                    } else {
                        echo "  ✅ Basic structure OK\n";
                    }
                    break;
                    
                case 'activities':
                    if ($columnCount < 12) {
                        echo "  ⚠️  May be missing columns (expected 12+)\n";
                    } else {
                        echo "  ✅ Column count looks OK\n";
                    }
                    break;
                    
                case 'purchase_orders':
                    if ($columnCount < 35) {
                        echo "  ⚠️  May be missing columns (expected 35+)\n";
                    } else {
                        echo "  ✅ Column count looks OK\n";
                    }
                    break;
            }
            
        } catch (Exception $e) {
            echo "  ❌ Could not analyze $table: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
