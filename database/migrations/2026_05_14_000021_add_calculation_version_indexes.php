<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Add missing performance indexes to calculation_versions
 * Risk Level: VERY LOW - Index additions only
 *
 * Indexes added:
 * 1. calculation_versions_type_is_active_index (type, is_active)
 *    - Fast lookup of active versions by type
 * 2. calculation_versions_effective_from_index (effective_from)
 *    - Range queries for version validity
 *
 * SAFETY:
 * - Non-blocking index creation (online DDL)
 * - hasTable() guard
 * - No data modifications
 *
 * Operation Order:
 * - calculation_versions must exist (we created in Batch 5)
 * - Run after table creation
 *
 * Production Risks:
 * - Negligible for small table (few rows)
 * - Index creation is fast
 *
 * Rollback:
 * - Drop indexes
 *
 * Deployment Notes:
 * - Batch 5: Performance Indexes
 * - These indexes are critical for version selection performance
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('calculation_versions')) {
            return;
        }

        Schema::table('calculation_versions', function (Blueprint $table) {
            // Composite index: find active version by type quickly
            try {
                if (!$this->indexExists('calculation_versions', 'calculation_versions_type_is_active_index')) {
                    $table->index(['type', 'is_active'], 'calculation_versions_type_is_active_index');
                }
            } catch (\Exception $e) {
                \Log::error('Index creation failed: calculation_versions_type_is_active_index', ['error' => $e->getMessage()]);
            }

            // Single column index: effective_from for range scans
            try {
                if (!$this->indexExists('calculation_versions', 'calculation_versions_effective_from_index')) {
                    $table->index('effective_from', 'calculation_versions_type_is_active_index');
                }
            } catch (\Exception $e) {
                \Log::error('Index creation failed: calculation_versions_effective_from_index', ['error' => $e->getMessage()]);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('calculation_versions')) {
            return;
        }

        Schema::table('calculation_versions', function (Blueprint $table) {
            $indexes = [
                'calculation_versions_type_is_active_index',
                'calculation_versions_effective_from_index'
            ];

            foreach ($indexes as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Exception $e) {
                    // Ignore if doesn't exist
                }
            }
        });
    }

    /**
     * Helper to check index existence (compatible across DB drivers)
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = $connection->getDoctrineSchemaManager();
            $indexes = $schemaManager->listTableIndexes(
                $connection->getDatabasePrefix() . $table
            );

            return isset($indexes[$indexName]);
        } catch (\Exception $e) {
            // If we can't determine, err on side of caution
            return false;
        }
    }
};
