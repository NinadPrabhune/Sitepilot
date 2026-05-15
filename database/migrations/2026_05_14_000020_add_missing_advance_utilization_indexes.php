<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Add missing performance indexes to advance_utilizations
 * Risk Level: VERY LOW - Index additions only
 *
 * CRITICAL CONTEXT:
 * - advance_utilizations is heavily queried for:
 *   - Supplier advance balance calculations (by supplier_advance_id)
 *   - Invoice utilization tracking (by purchase_invoice_id)
 *   - Transaction flow analysis (by transaction_flow_id)
 *
 * Indexes being added:
 * 1. idx_advance_status_amount (supplier_advance_id, status, utilized_amount)
 *    - Speeds up: advance balance queries filtering by status
 * 2. idx_invoice_status_amount (purchase_invoice_id, status, utilized_amount)
 *    - Speeds up: invoice utilization reporting
 * 3. idx_flow_created (transaction_flow_id, created_at)
 *    - Speeds up: transaction flow timeline queries
 *
 * SAFETY:
 * - Index creation is non-blocking in MySQL (online DDL)
 * - Uses IF NOT EXISTS logic inside try-catch for compatibility
 * - No data modifications - read-only performance improvement
 *
 * Operation Order:
 * - advance_utilizations table must exist (does in local and live)
 * - Independent addition
 *
 * Production Risks:
 * - Minimal: CREATE INDEX uses table lock briefly but online DDL reduces impact
 * - Consider off-peak deployment for large tables (> million rows)
 * - Index build time: seconds to minutes depending on row count
 * - Storage increase: ~3 indexes ~ size of table + overhead
 *
 * Rollback Safety:
 * - Simply drop the indexes (idempotent down method)
 *
 * Deployment Notes:
 * - Batch 5: Performance Indexes
 * - Verify advance_utilizations table exists before running
 * - Monitor query performance after deployment
 * - Consider using ALGORITHM=INPLACE, LOCK=NONE for large tables:
 *   ALTER TABLE advance_utilizations ADD INDEX ... ALGORITHM=INPLACE, LOCK=NONE;
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only proceed if table exists
        if (!Schema::hasTable('advance_utilizations')) {
            \Log::warning('advance_utilizations table not found - skipping index creation');
            return;
        }

        Schema::table('advance_utilizations', function (Blueprint $table) {
            // Index 1: Lookup advances by status and amount (for available balance queries)
            try {
                if (!$this->indexExists($table->getTable(), 'idx_advance_status_amount')) {
                    $table->index(
                        ['supplier_advance_id', 'status', 'utilized_amount'],
                        'idx_advance_status_amount'
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create idx_advance_status_amount: ' . $e->getMessage());
            }

            // Index 2: Lookup invoice utilizations with status filtering
            try {
                if (!$this->indexExists($table->getTable(), 'idx_invoice_status_amount')) {
                    $table->index(
                        ['purchase_invoice_id', 'status', 'utilized_amount'],
                        'idx_invoice_status_amount'
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create idx_invoice_status_amount: ' . $e->getMessage());
            }

            // Index 3: Transaction flow timeline queries
            try {
                if (!$this->indexExists($table->getTable(), 'idx_flow_created')) {
                    $table->index(
                        ['transaction_flow_id', 'created_at'],
                        'idx_flow_created'
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Failed to create idx_flow_created: ' . $e->getMessage());
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_utilizations', function (Blueprint $table) {
            $indexes = [
                'idx_advance_status_amount',
                'idx_invoice_status_amount',
                'idx_flow_created'
            ];

            foreach ($indexes as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Exception $e) {
                    // Index may not exist, ignore
                }
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $schemaManager = $connection->getDoctrineSchemaManager();
        $indexes = $schemaManager->listTableIndexes($connection->getDatabasePrefix() . $table);

        return isset($indexes[$indexName]);
    }
};
