<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Fixing missing columns in tables...\n\n";
    
    // Check activities table
    echo "=== CHECKING ACTIVITIES TABLE ===\n";
    $activitiesColumns = DB::select("DESCRIBE activities");
    $activitiesColumnNames = array_map(function($col) { return $col->Field; }, $activitiesColumns);
    
    echo "Current columns in activities table:\n";
    foreach ($activitiesColumnNames as $col) {
        echo "- $col\n";
    }
    
    // Expected columns based on migration
    $expectedActivitiesColumns = [
        'id', 'user_id', 'assign_to', 'reference_file', 'title', 
        'start_date', 'due_date', 'scope', 'quantity', 'unit', 
        'priority', 'status', 'created_by', 'workspace_id', 'site_id', 
        'created_at', 'updated_at'
    ];
    
    $missingActivitiesColumns = array_diff($expectedActivitiesColumns, $activitiesColumnNames);
    
    if (!empty($missingActivitiesColumns)) {
        echo "\n❌ Missing columns in activities table:\n";
        foreach ($missingActivitiesColumns as $col) {
            echo "- $col\n";
        }
        
        // Add missing columns
        foreach ($missingActivitiesColumns as $column) {
            switch ($column) {
                case 'user_id':
                    DB::statement("ALTER TABLE activities ADD COLUMN user_id BIGINT UNSIGNED NULL");
                    echo "✅ Added user_id column\n";
                    break;
            }
        }
    } else {
        echo "\n✅ All expected columns present in activities table\n";
    }
    
    echo "\n=== CHECKING PURCHASE_ORDERS TABLE ===\n";
    $purchaseOrdersColumns = DB::select("DESCRIBE purchase_orders");
    $purchaseOrdersColumnNames = array_map(function($col) { return $col->Field; }, $purchaseOrdersColumns);
    
    echo "Current columns in purchase_orders table:\n";
    foreach ($purchaseOrdersColumnNames as $col) {
        echo "- $col\n";
    }
    
    // Expected columns based on migration
    $expectedPurchaseOrdersColumns = [
        'id', 'transaction_flow_id', 'po_number', 'po_date', 'supplier_invoice_number', 
        'supplier_id', 'grand_total', 'invoiced_amount', 'invoiced_status', 
        'delivery_date', 'delivery_address', 'reference_file', 'delivery_terms_conditions', 
        'payment_terms_conditions', 'remark', 'assign_to', 'po_pdf', 'status', 
        'closed_date', 'tax_type', 'total_taxable_value', 'total_cgst', 'total_sgst', 
        'total_igst', 'total_tax', 'total_discount', 'additional_charge', 
        'additional_deduction', 'additional_discount', 'site_id', 'created_by', 
        'workspace_id', 'indent_id', 'description', 'total_amount', 'rejection_reason', 
        'rejected_at', 'cancelled_at', 'flag_reason', 'short_close_reason', 
        'short_closed_at', 'short_closed_by', 'payment_flag', 'created_at', 
        'updated_at', 'deleted_at', 'idempotency_key'
    ];
    
    $missingPurchaseOrdersColumns = array_diff($expectedPurchaseOrdersColumns, $purchaseOrdersColumnNames);
    
    if (!empty($missingPurchaseOrdersColumns)) {
        echo "\n❌ Missing columns in purchase_orders table:\n";
        foreach ($missingPurchaseOrdersColumns as $col) {
            echo "- $col\n";
        }
        
        // Add missing columns
        foreach ($missingPurchaseOrdersColumns as $column) {
            switch ($column) {
                case 'payment_terms_conditions':
                    DB::statement("ALTER TABLE purchase_orders ADD COLUMN payment_terms_conditions TEXT NULL");
                    echo "✅ Added payment_terms_conditions column\n";
                    break;
                case 'flag_reason':
                    DB::statement("ALTER TABLE purchase_orders ADD COLUMN flag_reason TEXT NULL");
                    echo "✅ Added flag_reason column\n";
                    break;
                case 'payment_flag':
                    DB::statement("ALTER TABLE purchase_orders ADD COLUMN payment_flag ENUM('pending', 'partial_received', 'fully_received') DEFAULT 'pending' COMMENT 'DEPRECATED: Use invoiced_status instead'");
                    echo "✅ Added payment_flag column\n";
                    break;
            }
        }
    } else {
        echo "\n✅ All expected columns present in purchase_orders table\n";
    }
    
    // Check if indexes exist
    echo "\n=== CHECKING INDEXES ===\n";
    
    $activitiesIndexes = DB::select("SHOW INDEX FROM activities");
    echo "Activities table indexes:\n";
    foreach ($activitiesIndexes as $index) {
        echo "- {$index->Key_name} ({$index->Column_name})\n";
    }
    
    $purchaseOrdersIndexes = DB::select("SHOW INDEX FROM purchase_orders");
    echo "\nPurchase Orders table indexes:\n";
    foreach ($purchaseOrdersIndexes as $index) {
        echo "- {$index->Key_name} ({$index->Column_name})\n";
    }
    
    echo "\n✅ Column fix completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
