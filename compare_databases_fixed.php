<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Comparing current database with backup database (Fixed)...\n\n";
    
    // Current database info
    $currentDb = 'sitepilot_local';
    echo "=== CURRENT DATABASE ($currentDb) ===\n";
    $currentTables = DB::select('SHOW TABLES');
    $currentTableNames = array_map(function($table) {
        return array_values((array)$table)[0];
    }, $currentTables);
    
    echo "Table count: " . count($currentTableNames) . "\n";
    
    // Check critical tables and their structures
    $criticalTables = ['users', 'activities', 'purchase_orders', 'work_spaces', 'settings'];
    
    foreach ($criticalTables as $table) {
        if (in_array($table, $currentTableNames)) {
            echo "✅ $table table exists\n";
            
            // Get column structure
            $columns = DB::select("DESCRIBE $table");
            $columnNames = array_map(function($col) {
                return $col->Field;
            }, $columns);
            
            echo "  Columns: " . implode(', ', $columnNames) . "\n";
            echo "  Count: " . DB::table($table)->count() . " records\n";
        } else {
            echo "❌ $table table missing\n";
        }
    }
    
    echo "\n=== CHECKING BACKUP DATABASE ===\n";
    
    // Check if backup database exists
    $backupDb = 'sitepilot_local_bk';
    
    try {
        // Connect to backup database
        $pdo = new PDO("mysql:host=localhost;dbname=$backupDb", 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "Connected to backup database: $backupDb\n";
        
        $backupTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Backup table count: " . count($backupTables) . "\n";
        
        // Compare critical tables
        echo "\nCritical tables comparison:\n";
        
        foreach ($criticalTables as $table) {
            $currentExists = in_array($table, $currentTableNames);
            $backupExists = in_array($table, $backupTables);
            
            if ($currentExists && $backupExists) {
                echo "✅ $table: Both databases\n";
                
                // Compare column structures
                try {
                    $currentColumns = DB::select("DESCRIBE $table");
                    $backupColumns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
                    
                    $currentColumnNames = array_map(function($col) {
                        return $col['Field'];
                    }, $currentColumns);
                    
                    $backupColumnNames = array_map(function($col) {
                        return $col['Field'];
                    }, $backupColumns);
                    
                    $missingColumns = array_diff($backupColumnNames, $currentColumnNames);
                    $extraColumns = array_diff($currentColumnNames, $backupColumnNames);
                    
                    if (!empty($missingColumns)) {
                        echo "  ❌ Missing columns in current: " . implode(', ', $missingColumns) . "\n";
                    }
                    
                    if (!empty($extraColumns)) {
                        echo "  ➕ Extra columns in current: " . implode(', ', $extraColumns) . "\n";
                    }
                    
                    if (empty($missingColumns) && empty($extraColumns)) {
                        echo "  ✅ Column structures match\n";
                    }
                    
                } catch (Exception $e) {
                    echo "  ⚠️  Could not compare columns: " . $e->getMessage() . "\n";
                }
                
            } elseif ($currentExists && !$backupExists) {
                echo "➕ $table: Only in current database\n";
            } elseif (!$currentExists && $backupExists) {
                echo "➖ $table: Only in backup database\n";
            } else {
                echo "❌ $table: Missing from both\n";
            }
        }
        
        $pdo = null;
        
    } catch (Exception $e) {
        echo "❌ Could not connect to backup database: " . $e->getMessage() . "\n";
        echo "Backup database may not exist or is not accessible\n";
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Current DB tables: " . count($currentTableNames) . "\n";
    echo "Expected from old: 277\n";
    
    if (count($currentTableNames) >= 277) {
        echo "✅ Current database has expected table count\n";
    } else {
        echo "⚠️  Current database has fewer tables than expected\n";
    }
    
    // Check for any column mismatches in current database
    echo "\nColumn mismatch analysis:\n";
    $totalMismatches = 0;
    
    foreach ($criticalTables as $table) {
        if (in_array($table, $currentTableNames)) {
            try {
                $columns = DB::select("DESCRIBE $table");
                $columnNames = array_map(function($col) {
                    return $col->Field;
                }, $columns);
                
                // Check for expected columns based on table type
                $expectedColumns = [];
                
                switch ($table) {
                    case 'users':
                        $expectedColumns = ['id', 'name', 'email', 'password', 'type', 'active_status', 'workspace_id', 'created_by', 'created_at', 'updated_at'];
                        break;
                    case 'activities':
                        $expectedColumns = ['id', 'user_id', 'assign_to', 'reference_file', 'title', 'start_date', 'due_date', 'scope', 'quantity', 'unit', 'priority', 'status', 'created_by', 'workspace_id', 'site_id', 'created_at', 'updated_at'];
                        break;
                    case 'purchase_orders':
                        $expectedColumns = ['id', 'transaction_flow_id', 'po_number', 'po_date', 'supplier_invoice_number', 'supplier_id', 'grand_total', 'delivery_date', 'delivery_address', 'reference_file', 'delivery_terms_conditions', 'payment_terms_conditions', 'remark', 'status', 'closed_date', 'tax_type', 'total_taxable_value', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax', 'total_discount', 'additional_charge', 'additional_deduction', 'additional_discount', 'site_id', 'created_by', 'workspace_id', 'indent_id', 'description', 'total_amount', 'assign_to', 'invoiced_amount', 'invoiced_status', 'po_pdf', 'rejection_reason', 'rejected_at', 'cancelled_at', 'short_close_reason', 'short_closed_at', 'short_closed_by', 'created_at', 'updated_at', 'deleted_at', 'idempotency_key', 'flag_reason', 'payment_flag'];
                        break;
                }
                
                $missingExpected = array_diff($expectedColumns, $columnNames);
                if (!empty($missingExpected)) {
                    echo "❌ $table missing expected columns: " . implode(', ', $missingExpected) . "\n";
                    $totalMismatches++;
                }
                
            } catch (Exception $e) {
                echo "⚠️  Could not analyze $table: " . $e->getMessage() . "\n";
            }
        }
    }
    
    if ($totalMismatches > 0) {
        echo "\n❌ Total column mismatches found: $totalMismatches\n";
    } else {
        echo "\n✅ No obvious column mismatches in critical tables\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
