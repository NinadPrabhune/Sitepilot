<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BATCH 6: DPR PERFOMANCE INDEXES - Phase 6
 * ============================================================================
 * PRIORITY: MEDIUM (Query optimization)
 *
 * MIGRATION: add_dpr_performance_indexes.php
 * TIMESTAMP: 2026_05_14_200006
 *
 * PURPOSE: Add performance indexes specifically for DPR queries
 *
 * INDEXES ADDED:
 * - daily_progress_reports: Status and date range queries
 * - daily_consumption_masters: Link to DPR and date queries
 * - ledger tables: Balance and date queries
 *
 * SAFETY RATIONALE:
 * - All index additions are non-destructive
 * - Uses try-catch to handle existing indexes
 * - No data modification
 * - Indexes improve query performance
 *
 * PRODUCTION RISK: LOW
 * - Index creation may briefly lock table (MySQL default)
 * - For zero-downtime, use pt-online-schema-change in production
 *
 * DEPLOYMENT NOTES:
 * - Run after Batch 5 (all tables/columns created)
 * - Monitor query performance before/after
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        // Get database connection for index checking
        $connection = Schema::getConnection();

        // =====================================================================
        // DAILY_PROGRESS_REPORTS INDEXES
        // =====================================================================
        if (Schema::hasTable('daily_progress_reports')) {
            try {
                $sm = $connection->getDoctrineSchemaManager();
                $tableName = $connection->getDatabasePrefix() . 'daily_progress_reports';
                $indexes = $sm->listTableIndexes($tableName);
                $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                Schema::table('daily_progress_reports', function (Blueprint $table) use ($existingIndexes) {
                    // Index for status filtering (common in DPR list views)
                    if (!in_array('idx_dpr_status', $existingIndexes)) {
                        $table->index('status', 'idx_dpr_status');
                    }

                    // Index for date range queries (reports, billing)
                    if (!in_array('idx_dpr_report_date', $existingIndexes) &&
                        Schema::hasColumn('daily_progress_reports', 'report_date')) {
                        $table->index(['report_date', 'status'], 'idx_dpr_report_date');
                    }

                    // Index for machinery-based queries
                    if (!in_array('idx_dpr_machinery_date', $existingIndexes) &&
                        Schema::hasColumn('daily_progress_reports', 'machinery_id')) {
                        $table->index(['machinery_id', 'report_date'], 'idx_dpr_machinery_date');
                    }

                    // Index for payment status queries
                    if (!in_array('idx_dpr_payment_status', $existingIndexes) &&
                        Schema::hasColumn('daily_progress_reports', 'payment_status')) {
                        $table->index(['payment_status', 'is_billed'], 'idx_dpr_payment_status');
                    }

                    // Index for workflow state queries
                    if (!in_array('idx_dpr_lifecycle_state', $existingIndexes) &&
                        Schema::hasColumn('daily_progress_reports', 'lifecycle_state')) {
                        $table->index('lifecycle_state', 'idx_dpr_lifecycle_state');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add daily_progress_reports indexes: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // DAILY_CONSUMPTION_MASTERS INDEXES
        // =====================================================================
        if (Schema::hasTable('daily_consumption_masters')) {
            try {
                $sm = $connection->getDoctrineSchemaManager();
                $tableName = $connection->getDatabasePrefix() . 'daily_consumption_masters';
                $indexes = $sm->listTableIndexes($tableName);
                $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                Schema::table('daily_consumption_masters', function (Blueprint $table) use ($existingIndexes) {
                    // Index for ledger linkage
                    if (!in_array('idx_dcm_ledger_entry', $existingIndexes) &&
                        Schema::hasColumn('daily_consumption_masters', 'ledger_entry_id')) {
                        $table->index('ledger_entry_id', 'idx_dcm_ledger_entry');
                    }

                    // Index for supplier ledger linkage
                    if (!in_array('idx_dcm_supplier_ledger_entry', $existingIndexes) &&
                        Schema::hasColumn('daily_consumption_masters', 'supplier_ledger_entry_id')) {
                        $table->index('supplier_ledger_entry_id', 'idx_dcm_supplier_ledger_entry');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add daily_consumption_masters indexes: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // LEDGER_ENTRIES INDEXES
        // =====================================================================
        if (Schema::hasTable('ledger_entries')) {
            try {
                $sm = $connection->getDoctrineSchemaManager();
                $tableName = $connection->getDatabasePrefix() . 'ledger_entries';
                $indexes = $sm->listTableIndexes($tableName);
                $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                Schema::table('ledger_entries', function (Blueprint $table) use ($existingIndexes) {
                    if (!in_array('idx_ledger_source', $existingIndexes)) {
                        $table->index(['source_type', 'source_id'], 'idx_ledger_source');
                    }

                    if (!in_array('idx_ledger_locked', $existingIndexes)) {
                        $table->index(['is_locked', 'entry_date'], 'idx_ledger_locked');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add ledger_entries indexes: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // MACHINERY_LEDGER INDEXES
        // =====================================================================
        if (Schema::hasTable('machinery_ledger')) {
            try {
                $sm = $connection->getDoctrineSchemaManager();
                $tableName = $connection->getDatabasePrefix() . 'machinery_ledger';
                $indexes = $sm->listTableIndexes($tableName);
                $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                Schema::table('machinery_ledger', function (Blueprint $table) use ($existingIndexes) {
                    // Composite index for balance calculation
                    if (!in_array('idx_ml_balance', $existingIndexes)) {
                        $table->index(['machinery_id', 'is_locked', 'entry_date'], 'idx_ml_balance');
                    }

                    // Index for billing queries
                    if (!in_array('idx_ml_billing', $existingIndexes) &&
                        Schema::hasColumn('machinery_ledger', 'billing_version')) {
                        $table->index(['machinery_id', 'billing_version', 'entry_date'], 'idx_ml_billing');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add machinery_ledger indexes: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // SUPPLIER_LEDGER INDEXES
        // =====================================================================
        if (Schema::hasTable('supplier_ledger')) {
            try {
                $sm = $connection->getDoctrineSchemaManager();
                $tableName = $connection->getDatabasePrefix() . 'supplier_ledger';
                $indexes = $sm->listTableIndexes($tableName);
                $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                Schema::table('supplier_ledger', function (Blueprint $table) use ($existingIndexes) {
                    // Composite for running balance queries
                    if (!in_array('idx_sl_balance', $existingIndexes)) {
                        $table->index(['supplier_id', 'is_reversal', 'date'], 'idx_sl_balance');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add supplier_ledger indexes: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // PAYMENT_REQUESTS INDEXES
        // =====================================================================
        if (Schema::hasTable('machinery_payment_requests')) {
            try {
                $sm = $connection->getDoctrineSchemaManager();
                $tableName = $connection->getDatabasePrefix() . 'machinery_payment_requests';
                $indexes = $sm->listTableIndexes($tableName);
                $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                Schema::table('machinery_payment_requests', function (Blueprint $table) use ($existingIndexes) {
                    // Index for status-based queries
                    if (!in_array('idx_mpr_status_date', $existingIndexes)) {
                        $table->index(['status', 'period_start', 'period_end'], 'idx_mpr_status_date');
                    }

                    // Index for payment processing
                    if (!in_array('idx_mpr_payment', $existingIndexes)) {
                        $table->index(['paid_at', 'status'], 'idx_mpr_payment');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add machinery_payment_requests indexes: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // PURCHASE_INVOICES INDEXES (if table exists)
        // =====================================================================
        if (Schema::hasTable('purchase_invoices')) {
            try {
                $sm = $connection->getDoctrineSchemaManager();
                $tableName = $connection->getDatabasePrefix() . 'purchase_invoices';
                $indexes = $sm->listTableIndexes($tableName);
                $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                Schema::table('purchase_invoices', function (Blueprint $table) use ($existingIndexes) {
                    if (!in_array('idx_pi_payment_status', $existingIndexes) &&
                        Schema::hasColumn('purchase_invoices', 'payment_status')) {
                        $table->index('payment_status', 'idx_pi_payment_status');
                    }

                    if (!in_array('idx_pi_financial_lock', $existingIndexes) &&
                        Schema::hasColumn('purchase_invoices', 'financial_locked_at')) {
                        $table->index(['financial_locked_at', 'payment_status'], 'idx_pi_financial_lock');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add purchase_invoices indexes: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Note: We don't drop indexes in rollback as they improve performance
    }
};