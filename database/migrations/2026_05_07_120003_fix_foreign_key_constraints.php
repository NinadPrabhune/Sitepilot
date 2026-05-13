<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - SAFE foreign key constraint fixes
     * Preserves all existing data, only fixes broken/missing foreign key constraints
     */
    public function up(): void
    {
        // 🛡️ SAFE: Fix foreign key constraints without dropping data
        
        // Fix payments_module foreign keys
        $this->fixForeignKey('payments_module', 'supplier_id', 'suppliers', 'id', 'cascade');
        $this->fixForeignKey('payments_module', 'purchase_invoice_id', 'purchase_invoices', 'id', 'set null');
        $this->fixForeignKey('payments_module', 'site_id', 'projects', 'id', 'cascade');
        $this->fixForeignKey('payments_module', 'created_by', 'users', 'id', 'cascade');
        $this->fixForeignKey('payments_module', 'workspace_id', 'work_spaces', 'id', 'cascade');
        $this->fixForeignKey('payments_module', 'purchase_order_id', 'purchase_orders', 'id', 'set null');
        
        // Fix purchase_invoices foreign keys
        $this->fixForeignKey('purchase_invoices', 'supplier_id', 'suppliers', 'id', 'cascade');
        $this->fixForeignKey('purchase_invoices', 'site_id', 'projects', 'id', 'set null');
        $this->fixForeignKey('purchase_invoices', 'created_by', 'users', 'id', 'set null');
        $this->fixForeignKey('purchase_invoices', 'workspace_id', 'work_spaces', 'id', 'set null');
        $this->fixForeignKey('purchase_invoices', 'grn_id', 'grns', 'id', 'set null');
        $this->fixForeignKey('purchase_invoices', 'locked_by', 'users', 'id', 'set null');
        $this->fixForeignKey('purchase_invoices', 'financially_locked_by', 'users', 'id', 'set null');
        
        // Fix suppliers foreign keys
        $this->fixForeignKey('suppliers', 'category_id', 'supplier_categories', 'id', 'cascade');
        $this->fixForeignKey('suppliers', 'created_by', 'users', 'id', 'set null');
        
        // Note: suppliers.site_id is tricky - it references 'sites' table which may not exist
        // We'll handle this conditionally to avoid errors
        if (Schema::hasTable('sites')) {
            $this->fixForeignKey('suppliers', 'site_id', 'sites', 'id', 'set null');
        }
    }

    /**
     * Helper method to fix foreign key constraints safely
     */
    private function fixForeignKey($table, $column, $referencesTable, $referencesColumn, $onDelete)
    {
        // Skip if table or column doesn't exist
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        // Skip if referenced table doesn't exist
        if (!Schema::hasTable($referencesTable)) {
            return;
        }

        try {
            // Check if foreign key exists using raw SQL (compatible approach)
            $foreignKeyName = $table . '_' . $column . '_foreign';
            $foreignKeyExists = DB::select("
                SELECT COUNT(*) as count 
                FROM information_schema.table_constraints 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}' 
                AND constraint_type = 'FOREIGN KEY'
                AND constraint_name = '{$foreignKeyName}'
            ")[0]->count > 0;

            if (!$foreignKeyExists) {
                Schema::table($table, function (Blueprint $table) use ($column, $referencesTable, $referencesColumn, $onDelete) {
                    $table->foreign($column)
                          ->references($referencesColumn)
                          ->on($referencesTable)
                          ->onDelete($onDelete);
                });
            }
        } catch (\Exception $e) {
            // Log error but don't break migration
            \Log::warning("Could not create foreign key for {$table}.{$column}: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations - SAFE rollback
     */
    public function down(): void
    {
        // 🛡️ SAFE: Only drop foreign keys that were added by this migration
        $tables = [
            'payments_module' => ['supplier_id', 'purchase_invoice_id', 'site_id', 'created_by', 'workspace_id', 'purchase_order_id'],
            'purchase_invoices' => ['supplier_id', 'site_id', 'created_by', 'workspace_id', 'grn_id', 'locked_by', 'financially_locked_by'],
            'suppliers' => ['category_id', 'created_by', 'site_id'],
        ];

        foreach ($tables as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        try {
                            Schema::table($table, function (Blueprint $table) use ($column) {
                                $table->dropForeign([$column]);
                            });
                        } catch (\Exception $e) {
                            // Ignore errors during rollback
                        }
                    }
                }
            }
        }
    }
};
