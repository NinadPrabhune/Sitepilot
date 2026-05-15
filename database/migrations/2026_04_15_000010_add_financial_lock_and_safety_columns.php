<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add financial lock state, idempotency key, and hard delete protection
     */
    public function up(): void
    {
        // Add financial lock state to purchase_invoices
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_invoices', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('status');
            }
            if (!Schema::hasColumn('purchase_invoices', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('is_locked');
            }
            if (!Schema::hasColumn('purchase_invoices', 'locked_by')) {
                $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');
            }
        });

        // Add idempotency key to payment_requests
        Schema::table('payment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_requests', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('id');
            }
            // Only add unique key if workspace_id exists
            if (Schema::hasColumn('payment_requests', 'workspace_id')) {
                if (!$this->indexExists('payment_requests', 'unique_idempotency_tenant')) {
                    $table->unique(['idempotency_key', 'workspace_id'], 'unique_idempotency_tenant');
                }
            } else {
                // Add unique key on idempotency_key only if workspace_id doesn't exist
                if (!$this->indexExists('payment_requests', 'unique_idempotency_key')) {
                    $table->unique('idempotency_key', 'unique_idempotency_key');
                }
            }
        });

        // Add hard delete protection (ON DELETE RESTRICT)
        // For advance_utilizations - only if table exists and has the columns
        if (Schema::hasTable('advance_utilizations')) {
            // Check if foreign keys exist before attempting to drop
            $supplierAdvanceFkExists = $this->foreignKeyExists('advance_utilizations', 'supplier_advance_id');
            $purchaseInvoiceFkExists = $this->foreignKeyExists('advance_utilizations', 'purchase_invoice_id');

            // Clean up orphaned records first
            DB::statement("
                DELETE FROM advance_utilizations
                WHERE supplier_advance_id NOT IN (SELECT id FROM supplier_advances)
            ");
            DB::statement("
                DELETE FROM advance_utilizations
                WHERE purchase_invoice_id NOT IN (SELECT id FROM purchase_invoices)
            ");

            Schema::table('advance_utilizations', function (Blueprint $table) use ($supplierAdvanceFkExists) {
                if (Schema::hasColumn('advance_utilizations', 'supplier_advance_id')) {
                    if ($supplierAdvanceFkExists) {
                        $table->dropForeign(['supplier_advance_id']);
                    }
                    $table->foreign('supplier_advance_id')
                        ->references('id')
                        ->on('supplier_advances')
                        ->onDelete('restrict');
                }
            });

            Schema::table('advance_utilizations', function (Blueprint $table) use ($purchaseInvoiceFkExists) {
                if (Schema::hasColumn('advance_utilizations', 'purchase_invoice_id')) {
                    if ($purchaseInvoiceFkExists) {
                        $table->dropForeign(['purchase_invoice_id']);
                    }
                    $table->foreign('purchase_invoice_id')
                        ->references('id')
                        ->on('purchase_invoices')
                        ->onDelete('restrict');
                }
            });
        }
    }

    /**
     * Check if a foreign key exists for a column
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as cnt
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        return isset($result[0]) && $result[0]->cnt > 0;
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as cnt
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
        ", [$table, $indexName]);

        return isset($result[0]) && $result[0]->cnt > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse hard delete protection
        Schema::table('advance_utilizations', function (Blueprint $table) {
            $table->dropForeign(['purchase_invoice_id']);
            $table->foreign('purchase_invoice_id')
                ->references('id')
                ->on('purchase_invoices')
                ->onDelete('cascade');
        });

        Schema::table('advance_utilizations', function (Blueprint $table) {
            $table->dropForeign(['supplier_advance_id']);
            $table->foreign('supplier_advance_id')
                ->references('id')
                ->on('supplier_advances')
                ->onDelete('cascade');
        });

        // Remove idempotency key
        Schema::table('payment_requests', function (Blueprint $table) {
            // Check if unique constraint exists before dropping it
            $constraintExists = DB::select("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'payment_requests' 
                AND INDEX_NAME = 'unique_idempotency_tenant'
            ")[0]->count > 0;
            
            if ($constraintExists) {
                $table->dropUnique('unique_idempotency_tenant');
            }
            
            if (Schema::hasColumn('payment_requests', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });

        // Remove financial lock state (only if columns exist)
        Schema::table('purchase_invoices', function (Blueprint $table) {
            foreach (['is_locked', 'locked_at', 'locked_by'] as $col) {
                if (Schema::hasColumn('purchase_invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
