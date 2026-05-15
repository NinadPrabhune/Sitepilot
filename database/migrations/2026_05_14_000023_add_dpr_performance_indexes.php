<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION - INDEX ADDITIONS
 *
 * Purpose: Add missing performance indexes to daily_progress_reports
 * Risk Level: VERY LOW - Index creation only
 *
 * Indexes added:
 * - idx_dpr_machinery_id: (machinery_id) - core lookup
 * - idx_dpr_site_id: (site_id) - site-based filtering
 * - idx_dpr_date: (date) - date range queries
 * - idx_dpr_workspace: (workspace_id, date) - workspace scoping
 * - idx_dpr_status: (status) - status filtering
 * - idx_dpr_payment_status: (payment_status, is_billed) - payment queries
 * - idx_dpr_calculation_hash: (calculation_hash) - duplicate detection
 * - idx_dpr_lifecycle: (lifecycle_state, verified_at, locked_at) - workflow tracking
 *
 * SAFETY:
 * - All indexes wrapped in existence checks
 * - Online DDL compatible
 * - No blocking operations
 *
 * Operation Order:
 * - Table must exist
 * - After columns added (payment_status, calculation_hash, lifecycle_state)
 *
 * Production Risks:
 * - Large table indexing could take time
 * - Recommend: run incrementally, monitor replication lag if any
 *
 * Rollback:
 * - Drop indexes individually
 *
 * Deployment Notes:
 * - Batch 5: Performance Indexes
 * - Run after column addition migrations
 * - Monitor query performance improvements
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_progress_reports')) {
            return;
        }

        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $indexes = [
                // Core entity lookups
                ['columns' => ['machinery_id'], 'name' => 'idx_dpr_machinery_id', 'type' => 'index'],
                ['columns' => ['site_id'], 'name' => 'idx_dpr_site_id', 'type' => 'index'],
                ['columns' => ['date'], 'name' => 'idx_dpr_date', 'type' => 'index'],
                ['columns' => ['workspace_id', 'date'], 'name' => 'idx_dpr_workspace_date', 'type' => 'index'],

                // Status-based filtering
                ['columns' => ['status'], 'name' => 'idx_dpr_status', 'type' => 'index'],
                ['columns' => ['payment_status', 'is_billed'], 'name' => 'idx_dpr_payment_filter', 'type' => 'index'],

                // Calculation integrity
                ['columns' => ['calculation_hash'], 'name' => 'idx_dpr_calculation_hash', 'type' => 'index'],

                // Lifecycle queries
                ['columns' => ['lifecycle_state'], 'name' => 'idx_dpr_lifecycle_state', 'type' => 'index'],
                ['columns' => ['lifecycle_state', 'verified_at'], 'name' => 'idx_dpr_verified_timeline', 'type' => 'index'],
                ['columns' => ['lifecycle_state', 'locked_at'], 'name' => 'idx_dpr_locked_timeline', 'type' => 'index'],

                // Approval workflows
                ['columns' => ['approved_by'], 'name' => 'idx_dpr_approved_by', 'type' => 'index'],
                ['columns' => ['rejected_by'], 'name' => 'idx_dpr_rejected_by', 'type' => 'index'],

                // Payment request linkage
                ['columns' => ['payment_request_id'], 'name' => 'idx_dpr_payment_request', 'type' => 'index'],

                // Timestamps for age-based queries
                ['columns' => ['created_at'], 'name' => 'idx_dpr_created_at', 'type' => 'index'],
            ];

            foreach ($indexes as $index) {
                try {
                    if (!$this->indexExists($table->getTable(), $index['name'])) {
                        if ($index['type'] === 'unique') {
                            $table->unique($index['columns'], $index['name']);
                        } else {
                            $table->index($index['columns'], $index['name']);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to create index {$index['name']}: " . $e->getMessage());
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $indexes = [
                'idx_dpr_machinery_id',
                'idx_dpr_site_id',
                'idx_dpr_date',
                'idx_dpr_workspace_date',
                'idx_dpr_status',
                'idx_dpr_payment_filter',
                'idx_dpr_calculation_hash',
                'idx_dpr_lifecycle_state',
                'idx_dpr_verified_timeline',
                'idx_dpr_locked_timeline',
                'idx_dpr_approved_by',
                'idx_dpr_rejected_by',
                'idx_dpr_payment_request',
                'idx_dpr_created_at',
            ];

            foreach ($indexes as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Exception $e) {
                    // ignore if doesn't exist
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = $connection->getDoctrineSchemaManager();
            $indexes = $schemaManager->listTableIndexes(
                $connection->getDatabasePrefix() . $table
            );

            foreach ($indexes as $index) {
                if (strcasecmp($index->getName(), $indexName) === 0) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};
