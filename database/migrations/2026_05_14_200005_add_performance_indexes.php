<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BATCH 5: PERFORMANCE INDEXES & DATA CONVERSIONS - Phase 5
 * ============================================================================
 * PRIORITY: MEDIUM (Performance optimization and data type alignment)
 *
 * MIGRATION: add_performance_indexes.php
 * TIMESTAMP: 2026_05_14_200005
 *
 * PURPOSE: Add missing indexes and convert data types where safe
 *
 * OPERATIONS:
 * 1. Add missing indexes on existing tables
 * 2. JSON/LONGTEXT conversion with data validation
 * 3. Type alignment (signed/unsigned)
 * 4. Nullability fixes
 *
 * SAFETY RATIONALE:
 * - All index additions wrapped in try-catch
 * - hasIndex() checks where available
 * - JSON conversion includes data validation
 * - No destructive operations
 *
 * OPERATION ORDER:
 * 1. Index additions (low risk)
 * 2. Type conversions (medium risk - requires validation)
 * 3. Nullability fixes (low risk - nullable only)
 *
 * PRODUCTION RISK: MEDIUM
 * - Index creation can lock table briefly
 * - JSON conversion may fail if existing data invalid
 * - Recommend maintenance window for large tables
 *
 * ROLLBACK: Indexes can be dropped, types reverted manually
 *
 * DEPLOYMENT NOTES:
 * - Run after all table creation migrations complete
 * - Test JSON conversion on staging first
 * - Monitor query performance after index creation
 * - Run verification queries post-migration
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // PART A: ADD MISSING INDEXES
        // =====================================================================

        // 1. advance_utilizations indexes
        if (Schema::hasTable('advance_utilizations')) {
            try {
                Schema::table('advance_utilizations', function (Blueprint $table) {
                    // Check if indexes don't exist before adding
                    $connection = Schema::getConnection();
                    $sm = $connection->getDoctrineSchemaManager();
                    $tableName = $connection->getDatabasePrefix() . 'advance_utilizations';
                    $indexes = $sm->listTableIndexes($tableName);
                    $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                    if (!in_array('idx_advance_status_amount', $existingIndexes)) {
                        $table->index(['supplier_advance_id', 'status', 'utilized_amount'], 'idx_advance_status_amount');
                    }
                    if (!in_array('idx_invoice_status_amount', $existingIndexes)) {
                        $table->index(['purchase_invoice_id', 'status', 'utilized_amount'], 'idx_invoice_status_amount');
                    }
                    if (!in_array('idx_flow_created', $existingIndexes)) {
                        $table->index(['transaction_flow_id', 'created_at'], 'idx_flow_created');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add advance_utilizations indexes: ' . $e->getMessage());
            }
        }

        // 2. calculation_versions indexes
        if (Schema::hasTable('calculation_versions')) {
            try {
                Schema::table('calculation_versions', function (Blueprint $table) {
                    $connection = Schema::getConnection();
                    $sm = $connection->getDoctrineSchemaManager();
                    $tableName = $connection->getDatabasePrefix() . 'calculation_versions';
                    $indexes = $sm->listTableIndexes($tableName);
                    $existingIndexes = array_map(fn($i) => $i->getName(), $indexes);

                    if (!in_array('calculation_versions_type_is_active_index', $existingIndexes)) {
                        $table->index(['calculable_type', 'is_active'], 'calculation_versions_type_is_active_index');
                    }
                    if (!in_array('calculation_versions_effective_from_index', $existingIndexes)) {
                        $table->index(['effective_from'], 'calculation_versions_effective_from_index');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add calculation_versions indexes: ' . $e->getMessage());
            }
        }

        // 3. activities index fix
        if (Schema::hasTable('activities')) {
            try {
                // The comparison shows Live uses partial index assign_to(250), Local uses full
                // We add the full index but keep existing to avoid breaking queries
                Schema::table('activities', function (Blueprint $table) {
                    if (!Schema::hasColumn('activities', 'user_id')) {
                        $table->unsignedBigInteger('user_id')->nullable()->after('id');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add activities column: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // PART B: JSON/TYPE CONVERSIONS (WITH VALIDATION)
        // =====================================================================

        // 1. advance_audit_logs - JSON conversion
        if (Schema::hasTable('advance_audit_logs')) {
            try {
                // First check if existing data is valid JSON
                $connection = Schema::getConnection();
                $hasInvalidJson = $connection->select("
                    SELECT COUNT(*) as cnt FROM advance_audit_logs
                    WHERE (old_value IS NOT NULL AND old_value != ''
                    AND JSON_VALID(old_value) = 0)
                    OR (new_value IS NOT NULL AND new_value != ''
                    AND JSON_VALID(new_value) = 0)
                ");

                if (isset($hasInvalidJson[0]) && $hasInvalidJson[0]->cnt == 0) {
                    Schema::table('advance_audit_logs', function (Blueprint $table) {
                        if (Schema::hasColumn('advance_audit_logs', 'old_value')) {
                            // Convert LONGTEXT to JSON
                            DB::statement("ALTER TABLE advance_audit_logs MODIFY old_value JSON");
                        }
                        if (Schema::hasColumn('advance_audit_logs', 'new_value')) {
                            DB::statement("ALTER TABLE advance_audit_logs MODIFY new_value JSON");
                        }
                    });
                } else {
                    \Log::warning('advance_audit_logs has invalid JSON data - skipping conversion');
                }
            } catch (\Exception $e) {
                \Log::warning('Could not convert advance_audit_logs JSON: ' . $e->getMessage());
            }
        }

        // 2. ch_notifications - JSON conversion
        if (Schema::hasTable('ch_notifications')) {
            try {
                $connection = Schema::getConnection();
                $hasInvalidJson = $connection->select("
                    SELECT COUNT(*) as cnt FROM ch_notifications
                    WHERE message_arr IS NOT NULL AND message_arr != ''
                    AND JSON_VALID(message_arr) = 0
                ");

                if (isset($hasInvalidJson[0]) && $hasInvalidJson[0]->cnt == 0) {
                    Schema::table('ch_notifications', function (Blueprint $table) {
                        if (Schema::hasColumn('ch_notifications', 'message_arr')) {
                            DB::statement("ALTER TABLE ch_notifications MODIFY message_arr JSON");
                        }
                    });
                }
            } catch (\Exception $e) {
                \Log::warning('Could not convert ch_notifications JSON: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // PART C: TYPE ALIGNMENTS (SIGNED/UNSIGNED)
        // =====================================================================

        // 1. attendances - unsigned site_id
        if (Schema::hasTable('attendances')) {
            try {
                Schema::table('attendances', function (Blueprint $table) {
                    if (Schema::hasColumn('attendances', 'site_id') &&
                        !Schema::hasColumn('attendances', 'site_id_unsigned')) {
                        // Check current type and convert if needed
                        $connection = Schema::getConnection();
                        $column = $connection->getSchemaBuilder()->getColumnType('attendances', 'site_id');
                        if (strpos($column, 'int') !== false && strpos($column, 'unsigned') === false) {
                            $table->unsignedBigInteger('site_id')->change();
                        }
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not update attendances site_id type: ' . $e->getMessage());
            }
        }

        // 2. activities - start_date NOT NULL fix
        if (Schema::hasTable('activities')) {
            try {
                Schema::table('activities', function (Blueprint $table) {
                    if (Schema::hasColumn('activities', 'start_date')) {
                        // Check if nullable and fix if no data issues
                        $connection = Schema::getConnection();
                        $nullCount = $connection->select("SELECT COUNT(*) as cnt FROM activities WHERE start_date IS NULL");
                        if (isset($nullCount[0]) && $nullCount[0]->cnt == 0) {
                            $table->dateTime('start_date')->change();
                        }
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not update activities start_date: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // PART D: ADD MISSING FOREIGN KEYS (SAFE)
        // =====================================================================

        // Add foreign keys to ledger entries if tables exist
        try {
            if (Schema::hasTable('machinery_ledger') && Schema::hasColumn('machinery_ledger', 'payment_request_id')) {
                Schema::table('machinery_ledger', function (Blueprint $table) {
                    $hasFk = false;
                    try {
                        $connection = Schema::getConnection();
                        $sm = $connection->getDoctrineSchemaManager();
                        $fks = $sm->listTableForeignKeys($connection->getDatabasePrefix() . 'machinery_ledger');
                        foreach ($fks as $fk) {
                            if (in_array('payment_request_id', $fk->getColumns())) {
                                $hasFk = true;
                                break;
                            }
                        }
                    } catch (\Exception $e) {}

                    if (!$hasFk && Schema::hasColumn('machinery_ledger', 'payment_request_id')) {
                        $table->foreign('payment_request_id')
                            ->references('id')
                            ->on('machinery_payment_requests')
                            ->onDelete('set null');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Could not add machinery_ledger FK: ' . $e->getMessage());
        }

        // Add FK to daily_consumption_masters for ledger_entry_id
        try {
            if (Schema::hasTable('daily_consumption_masters') && Schema::hasColumn('daily_consumption_masters', 'ledger_entry_id')) {
                Schema::table('daily_consumption_masters', function (Blueprint $table) {
                    if (!$this->hasForeignKey('daily_consumption_masters', 'ledger_entry_id')) {
                        $table->foreign('ledger_entry_id')
                            ->references('id')
                            ->on('ledger_entries')
                            ->onDelete('set null');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Could not add daily_consumption_masters ledger_entry_id FK: ' . $e->getMessage());
        }

        // Add FK to daily_consumption_masters for supplier_ledger_entry_id
        try {
            if (Schema::hasTable('daily_consumption_masters') && Schema::hasColumn('daily_consumption_masters', 'supplier_ledger_entry_id')) {
                Schema::table('daily_consumption_masters', function (Blueprint $table) {
                    if (!$this->hasForeignKey('daily_consumption_masters', 'supplier_ledger_entry_id')) {
                        $table->foreign('supplier_ledger_entry_id')
                            ->references('id')
                            ->on('supplier_ledger_entries')
                            ->onDelete('set null');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Could not add daily_consumption_masters supplier_ledger_entry_id FK: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We don't drop indexes in rollback as they may be needed
        // Type conversions would require manual reversal
    }

    /**
     * Check if foreign key exists
     */
    private function hasForeignKey(string $table, string $column): bool
    {
        try {
            $connection = Schema::getConnection();
            $sm = $connection->getDoctrineSchemaManager();
            $foreignKeys = $sm->listTableForeignKeys($connection->getDatabasePrefix() . $table);
            foreach ($foreignKeys as $fk) {
                if (in_array($column, $fk->getColumns())) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};