<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add transaction_flow_id and grn_type to all relevant tables
     * for full lifecycle tracking and audit trail
     */
    public function up(): void
    {
        // purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'transaction_flow_id')) {
                $table->string('transaction_flow_id', 50)->nullable()->after('id');
                $table->index('transaction_flow_id', 'idx_po_transaction_flow');
            }
        });

        // purchase_invoices
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_invoices', 'po_id')) {
                $table->unsignedBigInteger('po_id')->nullable()->after('supplier_id');
                $table->foreign('po_id')->references('id')->on('purchase_orders')->nullOnDelete();
                $table->index('po_id');
            }
            if (!Schema::hasColumn('purchase_invoices', 'transaction_flow_id')) {
                $table->string('transaction_flow_id', 50)->nullable()->after('po_id');
                $table->index('transaction_flow_id', 'idx_invoice_transaction_flow');
            }
            if (!Schema::hasColumn('purchase_invoices', 'grn_type')) {
                $table->enum('grn_type', ['PO', 'DIRECT'])->default('PO')->after('transaction_flow_id');
            }
        });

        // payment_requests
        Schema::table('payment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_requests', 'transaction_flow_id')) {
                $table->string('transaction_flow_id', 50)->nullable()->after('id');
                $table->index('transaction_flow_id', 'idx_payment_request_transaction_flow');
            }
        });

        // supplier_advances
        Schema::table('supplier_advances', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_advances', 'transaction_flow_id')) {
                $table->string('transaction_flow_id', 50)->nullable()->after('id');
                $table->index('transaction_flow_id', 'idx_advance_transaction_flow');
            }
            if (!Schema::hasColumn('supplier_advances', 'locked_to_po')) {
                $table->boolean('locked_to_po')->default(false)->after('po_id');
            }
        });

        // advance_utilizations
        Schema::table('advance_utilizations', function (Blueprint $table) {
            if (!Schema::hasColumn('advance_utilizations', 'transaction_flow_id')) {
                $table->string('transaction_flow_id', 50)->nullable()->after('id');
                $table->index('transaction_flow_id', 'idx_utilization_transaction_flow');
            }
            if (!Schema::hasColumn('advance_utilizations', 'status')) {
                $table->enum('status', ['reserved', 'applied', 'reversed'])->default('applied')->after('utilized_amount');
                $table->timestamp('reserved_at')->nullable()->after('status');
                $table->timestamp('applied_at')->nullable()->after('reserved_at');
                $table->timestamp('reversed_at')->nullable()->after('applied_at');
                $table->index('status');
            }
        });

        // supplier_transactions (if exists)
        if (Schema::hasTable('supplier_transactions')) {
            Schema::table('supplier_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_transactions', 'transaction_flow_id')) {
                    $table->string('transaction_flow_id', 50)->nullable()->after('id');
                    $table->index('transaction_flow_id', 'idx_transaction_transaction_flow');
                }
                if (!Schema::hasColumn('supplier_transactions', 'grn_type')) {
                    $table->enum('grn_type', ['PO', 'DIRECT'])->nullable()->after('transaction_flow_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse in opposite order
        if (Schema::hasTable('supplier_transactions')) {
            Schema::table('supplier_transactions', function (Blueprint $table) {
                // Check if index exists before dropping it
                $indexExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'supplier_transactions' 
                    AND INDEX_NAME = 'idx_transaction_transaction_flow'
                ")[0]->count > 0;
                
                if ($indexExists) {
                    $table->dropIndex('idx_transaction_transaction_flow');
                }
                
                if (Schema::hasColumn('supplier_transactions', 'transaction_flow_id')) {
                    $table->dropColumn('transaction_flow_id');
                }
                if (Schema::hasColumn('supplier_transactions', 'grn_type')) {
                    $table->dropColumn('grn_type');
                }
            });
        }

        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Check each index individually before dropping
            $indexes = ['idx_utilization_transaction_flow', 'status'];
            
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
            
            // Drop columns if they exist
            $columns = ['transaction_flow_id', 'status', 'reserved_at', 'applied_at', 'reversed_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('advance_utilizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('supplier_advances', function (Blueprint $table) {
            // Check if index exists before dropping it
            $indexExists = DB::select("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'supplier_advances' 
                AND INDEX_NAME = 'idx_advance_transaction_flow'
            ")[0]->count > 0;
            
            if ($indexExists) {
                $table->dropIndex('idx_advance_transaction_flow');
            }
            
            // Drop columns if they exist
            if (Schema::hasColumn('supplier_advances', 'transaction_flow_id')) {
                $table->dropColumn('transaction_flow_id');
            }
            if (Schema::hasColumn('supplier_advances', 'locked_to_po')) {
                $table->dropColumn('locked_to_po');
            }
        });

        Schema::table('payment_requests', function (Blueprint $table) {
            // Check if index exists before dropping it
            $indexExists = DB::select("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'payment_requests' 
                AND INDEX_NAME = 'idx_payment_request_transaction_flow'
            ")[0]->count > 0;
            
            if ($indexExists) {
                $table->dropIndex('idx_payment_request_transaction_flow');
            }
            
            if (Schema::hasColumn('payment_requests', 'transaction_flow_id')) {
                $table->dropColumn('transaction_flow_id');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            // Check indexes before dropping
            $indexes = ['idx_invoice_transaction_flow', 'po_id'];
            foreach ($indexes as $indexName) {
                $indexExists = DB::select("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'purchase_invoices' 
                    AND INDEX_NAME = '$indexName'
                ")[0]->count > 0;
                
                if ($indexExists) {
                    $table->dropIndex($indexName);
                }
            }
            
            // Drop foreign key if it exists
            try {
                $table->dropForeign(['po_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
            
            // Drop columns if they exist
            $columns = ['po_id', 'transaction_flow_id', 'grn_type'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('purchase_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Check if index exists before dropping it
            $indexExists = DB::select("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'purchase_orders' 
                AND INDEX_NAME = 'idx_po_transaction_flow'
            ")[0]->count > 0;
            
            if ($indexExists) {
                $table->dropIndex('idx_po_transaction_flow');
            }
            
            if (Schema::hasColumn('purchase_orders', 'transaction_flow_id')) {
                $table->dropColumn('transaction_flow_id');
            }
        });
    }
};
