<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add covering indexes for performance optimization
     */
    public function up(): void
    {
        // For advance utilization queries (status-based)
        Schema::table('advance_utilizations', function (Blueprint $table) {
            $table->index(['supplier_advance_id', 'status', 'utilized_amount'], 'idx_advance_status_amount');
            $table->index(['purchase_invoice_id', 'status', 'utilized_amount'], 'idx_invoice_status_amount');
            $table->index(['transaction_flow_id', 'created_at'], 'idx_flow_created');
        });

        // For supplier_advances queries
        Schema::table('supplier_advances', function (Blueprint $table) {
            $table->index(['po_id', 'supplier_id', 'workspace_id', 'site_id'], 'idx_po_supplier_workspace_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_advances', function (Blueprint $table) {
            // Check if index exists before dropping it
            $indexExists = DB::select("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'supplier_advances' 
                AND INDEX_NAME = 'idx_po_supplier_workspace_site'
            ")[0]->count > 0;
            
            if ($indexExists) {
                $table->dropIndex('idx_po_supplier_workspace_site');
            }
        });

        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Check each index individually before dropping
            $indexes = ['idx_advance_status_amount', 'idx_invoice_status_amount', 'idx_flow_created'];
            
            foreach ($indexes as $indexName) {
                $indexExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'advance_utilizations' 
                    AND INDEX_NAME = '$indexName'
                ")[0]->count > 0;
                
                if ($indexExists) {
                    $table->dropIndex($indexName);
                }
            }
        });
    }
};
