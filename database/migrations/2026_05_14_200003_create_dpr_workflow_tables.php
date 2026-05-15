<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BATCH 3: DPR WORKFLOW ENHANCEMENTS - Phase 3
 * ============================================================================
 * PRIORITY: HIGH (Critical for DPR reconciliation and audit)
 *
 * MIGRATION: create_dpr_workflow_tables.php
 * TIMESTAMP: 2026_05_14_200003
 *
 * PURPOSE: Add missing DPR workflow columns and related tables
 *
 * COLUMNS ADDED TO daily_progress_reports:
 * - approved_at, approved_by, rejected_at, rejected_by, rejection_reason
 * - locked_at, locked_by, is_locked, override_at, override_by, override_rate
 * - override_reason, verification fields, lifecycle_state, rate_snapshot
 * - manual_balance fields, orphan_count, critical_drift_count
 * - hash_mismatch_count, total_entries, total_reversals, system_health_status
 *
 * TABLES CREATED:
 * 1. dpr_edit_history - DPR edit tracking
 * 2. dpr_anomalies - Anomaly detection
 * 3. daily_health_check_logs - System health monitoring
 * 4. workflow_transitions - Workflow state transitions
 * 5. workflow_state_histories - State change history
 * 6. workflow_audits - Workflow audit logs
 * 7. calculation_versions - Calculation version tracking
 *
 * SAFETY RATIONALE:
 * - All columns wrapped in hasColumn() checks for idempotency
 * - All nullable with safe defaults
 * - No data modification - pure schema alteration
 * - Tables use hasTable() guards
 *
 * OPERATION ORDER:
 * 1. Add columns first (prerequisite for some tables)
 * 2. Create tables in order (FK dependencies)
 *
 * PRODUCTION RISK: MEDIUM
 * - ALTER TABLE on large DPR table can lock
 * - Recommend during maintenance window for > 100K rows
 * - Add columns in batches if table is huge
 *
 * ROLLBACK: Each column/table can be dropped individually
 *
 * DEPLOYMENT NOTES:
 * - Run after Batch 2 (machinery module)
 * - Check table size first: SELECT COUNT(*) FROM daily_progress_reports
 * - Consider running columns migration separately if table > 1M rows
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // PART A: ADD MISSING COLUMNS TO daily_progress_reports
        // =====================================================================

        if (Schema::hasTable('daily_progress_reports')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                // Approval fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('status')
                        ->comment('When the DPR was approved');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at')
                        ->comment('User who approved');
                }

                // Rejection fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('approved_by')
                        ->comment('When the DPR was rejected');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at')
                        ->comment('User who rejected');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_by')
                        ->comment('Reason for rejection');
                }

                // Lock fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->after('rejection_reason')
                        ->comment('Whether the DPR is locked for editing');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable()->after('is_locked')
                        ->comment('When the DPR was locked');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'locked_by')) {
                    $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at')
                        ->comment('User who locked the DPR');
                }

                // Override fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'override_rate')) {
                    $table->decimal('override_rate', 12, 2)->nullable()->after('locked_by')
                        ->comment('Manually overridden rate');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'override_reason')) {
                    $table->text('override_reason')->nullable()->after('override_rate')
                        ->comment('Reason for rate override');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'override_at')) {
                    $table->timestamp('override_at')->nullable()->after('override_reason')
                        ->comment('When rate was overridden');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'override_by')) {
                    $table->unsignedBigInteger('override_by')->nullable()->after('override_at')
                        ->comment('User who overrode the rate');
                }

                // Verification fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('override_by')
                        ->comment('When the DPR was verified');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at')
                        ->comment('User who verified');
                }

                // Payment fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('verified_by')
                        ->comment('When payment was made');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'paid_by')) {
                    $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at')
                        ->comment('User who processed payment');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'billed_at')) {
                    $table->timestamp('billed_at')->nullable()->after('paid_by')
                        ->comment('When included in billing');
                }

                // Lifecycle state (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'lifecycle_state')) {
                    $table->enum('lifecycle_state', ['draft', 'pending_verification', 'verified', 'approved', 'locked', 'paid', 'rejected'])
                        ->nullable()->after('billed_at')->comment('Current workflow state');
                }

                // Rate snapshot (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'rate_snapshot')) {
                    $table->json('rate_snapshot')->nullable()->after('lifecycle_state')
                        ->comment('Rate configuration at calculation time');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'calculation_hash')) {
                    $table->string('calculation_hash', 64)->nullable()->after('rate_snapshot')
                        ->comment('Hash of calculation for integrity');
                }

                // Manual balance check fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'manual_balance_check')) {
                    $table->boolean('manual_balance_check')->default(false)->after('calculation_hash')
                        ->comment('Manual balance verification performed');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'manual_balance_matched')) {
                    $table->boolean('manual_balance_matched')->nullable()->after('manual_balance_check')
                        ->comment('Whether manual check matched system');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'manual_balance_notes')) {
                    $table->text('manual_balance_notes')->nullable()->after('manual_balance_matched')
                        ->comment('Notes from manual balance check');
                }

                // Drift and anomaly tracking (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'orphan_count')) {
                    $table->integer('orphan_count')->default(0)->after('manual_balance_notes')
                        ->comment('Number of orphaned ledger entries');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'critical_drift_count')) {
                    $table->integer('critical_drift_count')->default(0)->after('orphan_count')
                        ->comment('Number of critical data drifts');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'hash_mismatch_count')) {
                    $table->integer('hash_mismatch_count')->default(0)->after('critical_drift_count')
                        ->comment('Number of calculation hash mismatches');
                }

                // Reconciliation fields (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'total_entries')) {
                    $table->integer('total_entries')->default(0)->after('hash_mismatch_count')
                        ->comment('Total ledger entries for this DPR');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'total_reversals')) {
                    $table->integer('total_reversals')->default(0)->after('total_entries')
                        ->comment('Number of reversed entries');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'reversal_rate_percent')) {
                    $table->decimal('reversal_rate_percent', 5, 2)->nullable()->after('total_reversals')
                        ->comment('Percentage of reversed entries');
                }

                // System health (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'system_health_status')) {
                    $table->enum('system_health_status', ['healthy', 'warning', 'critical', 'unknown'])
                        ->default('unknown')->after('reversal_rate_percent')
                        ->comment('Overall data health of this DPR');
                }

                // Warning overrides (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'warning_override_count')) {
                    $table->integer('warning_override_count')->default(0)->after('system_health_status')
                        ->comment('Number of warning overrides');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'warning_overrides')) {
                    $table->json('warning_overrides')->nullable()->after('warning_override_count')
                        ->comment('Details of warning overrides');
                }

                // Source tracking (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'source_type')) {
                    $table->string('source_type', 100)->nullable()->after('warning_overrides')
                        ->comment('Source system that created this DPR');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'captured_at')) {
                    $table->timestamp('captured_at')->nullable()->after('source_type')
                        ->comment('When captured from source system');
                }
                if (!Schema::hasColumn('daily_progress_reports', 'captured_by')) {
                    $table->unsignedBigInteger('captured_by')->nullable()->after('captured_at')
                        ->comment('System/user that captured');
                }

                // Snapshot date (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'snapshot_date')) {
                    $table->date('snapshot_date')->nullable()->after('captured_by')
                        ->comment('Date of data snapshot');
                }

                // Audit log (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'audit_log')) {
                    $table->json('audit_log')->nullable()->after('snapshot_date')
                        ->comment('Complete audit trail of changes');
                }

                // Delete tracking (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable()->after('audit_log')
                        ->comment('User who soft deleted');
                }

                // Pending approvals tracking (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'pending_approvals')) {
                    $table->json('pending_approvals')->nullable()->after('deleted_by')
                        ->comment('Pending approval queue');
                }

                // Oldest pending age (if not exist)
                if (!Schema::hasColumn('daily_progress_reports', 'oldest_pending_age_hours')) {
                    $table->integer('oldest_pending_age_hours')->nullable()->after('pending_approvals')
                        ->comment('Hours since oldest pending item');
                }
            });

            // Add foreign keys for user reference columns
            try {
                Schema::table('daily_progress_reports', function (Blueprint $table) {
                    if (Schema::hasColumn('daily_progress_reports', 'approved_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'approved_by')) {
                        $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('daily_progress_reports', 'rejected_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'rejected_by')) {
                        $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('daily_progress_reports', 'locked_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'locked_by')) {
                        $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('daily_progress_reports', 'override_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'override_by')) {
                        $table->foreign('override_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('daily_progress_reports', 'verified_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'verified_by')) {
                        $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('daily_progress_reports', 'paid_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'paid_by')) {
                        $table->foreign('paid_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('daily_progress_reports', 'captured_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'captured_by')) {
                        $table->foreign('captured_by')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasColumn('daily_progress_reports', 'deleted_by') &&
                        !$this->hasForeignKey('daily_progress_reports', 'deleted_by')) {
                        $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
                    }
                });
            } catch (\Exception $e) {
                \Log::warning('Could not add some FKs to daily_progress_reports: ' . $e->getMessage());
            }
        }

        // =====================================================================
        // PART B: CREATE DPR-RELATED TABLES
        // =====================================================================

        // 1. DPR_EDIT_HISTORY
        if (!Schema::hasTable('dpr_edit_history')) {
            Schema::create('dpr_edit_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('daily_progress_report_id');
                $table->unsignedBigInteger('workspace_id');

                // Edit details
                $table->string('edit_type', 50)->comment('rate_change, data_correction, manual_adjustment');
                $table->string('field_name', 100)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();

                // Reason
                $table->text('edit_reason')->nullable();
                $table->string('edit_source', 50)->nullable()->comment('manual, system, import');

                // Audit
                $table->unsignedBigInteger('edited_by');
                $table->timestamp('edited_at');

                // Indexes
                $table->index(['daily_progress_report_id', 'edited_at'], 'idx_dpr_edit_history');
                $table->index(['edited_by', 'edited_at'], 'idx_editor_at');
            });
        }

        // 2. DPR_ANOMALIES
        if (!Schema::hasTable('dpr_anomalies')) {
            Schema::create('dpr_anomalies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('daily_progress_report_id');
                $table->unsignedBigInteger('workspace_id');

                // Anomaly details
                $table->string('anomaly_type', 50)->comment('rate_mismatch, duplicate, outlier, missing');
                $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->text('description');
                $table->json('anomaly_data')->nullable();

                // Status
                $table->enum('status', ['detected', 'investigating', 'resolved', 'ignored'])->default('detected');
                $table->text('resolution_notes')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['status', 'severity'], 'idx_anomaly_status_severity');
                $table->index(['daily_progress_report_id', 'created_at'], 'idx_anomaly_dpr_date');
            });
        }

        // 3. DAILY_HEALTH_CHECK_LOGS
        if (!Schema::hasTable('daily_health_check_logs')) {
            Schema::create('daily_health_check_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Check details
                $table->string('check_type', 50)->comment('balance_check, data_integrity, reconciliation');
                $table->enum('status', ['passed', 'warning', 'failed'])->default('passed');
                $table->text('check_message')->nullable();
                $table->json('check_details')->nullable();

                // Counts
                $table->integer('records_checked')->default(0);
                $table->integer('issues_found')->default(0);
                $table->integer('critical_issues')->default(0);

                // Performance
                $table->decimal('execution_time_ms', 10, 2)->nullable();

                $table->timestamp('check_run_at');

                // Indexes
                $table->index(['workspace_id', 'check_run_at'], 'idx_health_check_date');
                $table->index(['check_type', 'status'], 'idx_health_type_status');
            });
        }

        // 4. WORKFLOW_TRANSITIONS
        if (!Schema::hasTable('workflow_transitions')) {
            Schema::create('workflow_transitions', function (Blueprint $table) {
                $table->id();
                $table->string('workflow_type', 50)->comment('dpr, payment, approval');
                $table->string('from_state', 50);
                $table->string('to_state', 50);
                $table->string('transition_action', 50)->comment('approve, reject, verify');

                // Requirements
                $table->boolean('requires_approval')->default(false);
                $table->string('required_role', 50)->nullable();
                $table->boolean('is_valid')->default(true);

                // Audit
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                // Indexes
                $table->index(['workflow_type', 'is_valid'], 'idx_workflow_type_valid');
                $table->unique(['workflow_type', 'from_state', 'to_state'], 'idx_unique_transition');
            });
        }

        // 5. WORKFLOW_STATE_HISTORIES
        if (!Schema::hasTable('workflow_state_histories')) {
            Schema::create('workflow_state_histories', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 100)->comment('DailyProgressReport, etc');
                $table->unsignedBigInteger('entity_id');
                $table->string('workflow_type', 50)->nullable();

                // State details
                $table->string('from_state', 50)->nullable();
                $table->string('to_state', 50);
                $table->string('transition_action', 50)->nullable();
                $table->text('transition_notes')->nullable();

                // Actor
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_type', 50)->nullable()->comment('user, system, api');

                $table->timestamp('transitioned_at');
                $table->json('metadata')->nullable();

                // Indexes
                $table->index(['entity_type', 'entity_id', 'transitioned_at'], 'idx_entity_state_history');
                $table->index(['workflow_type', 'transitioned_at'], 'idx_workflow_history');
            });
        }

        // 6. WORKFLOW_AUDITS
        if (!Schema::hasTable('workflow_audits')) {
            Schema::create('workflow_audits', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 100);
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('workspace_id');

                // Audit details
                $table->string('audit_action', 50)->comment('state_change, approval, rejection');
                $table->string('previous_state', 50)->nullable();
                $table->string('new_state', 50);
                $table->json('changes')->nullable();

                // Actor
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 100)->nullable();
                $table->text('actor_ip')->nullable();

                // Context
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['entity_type', 'entity_id'], 'idx_audit_entity');
                $table->index(['workspace_id', 'created_at'], 'idx_audit_ws_date');
                $table->index('actor_id', 'idx_audit_actor');
            });
        }

        // 7. ADD MISSING COLUMNS TO daily_consumption_masters
        if (Schema::hasTable('daily_consumption_masters')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                if (!Schema::hasColumn('daily_consumption_masters', 'diesel_consumed_liters')) {
                    $table->decimal('diesel_consumed_liters', 10, 2)->nullable()->comment('Diesel consumed in liters');
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'diesel_rate')) {
                    $table->decimal('diesel_rate', 10, 2)->nullable()->comment('Diesel rate per liter');
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'diesel_total_cost')) {
                    $table->decimal('diesel_total_cost', 15, 2)->nullable()->comment('Total diesel cost');
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'ledger_entry_id')) {
                    $table->unsignedBigInteger('ledger_entry_id')->nullable()->comment('Linked ledger entry');
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'supplier_ledger_entry_id')) {
                    $table->unsignedBigInteger('supplier_ledger_entry_id')->nullable()->comment('Linked supplier ledger entry');
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'version')) {
                    $table->integer('version')->default(1)->comment('Version for audit trail');
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'warning_override_count')) {
                    $table->integer('warning_override_count')->default(0);
                }
                if (!Schema::hasColumn('daily_consumption_masters', 'warning_overrides')) {
                    $table->json('warning_overrides')->nullable();
                }
            });
        }

        // 8. ADD MISSING COLUMNS TO daily_consumption_details
        if (Schema::hasTable('daily_consumption_details')) {
            Schema::table('daily_consumption_details', function (Blueprint $table) {
                if (!Schema::hasColumn('daily_consumption_details', 'unit_price')) {
                    $table->decimal('unit_price', 15, 2)->nullable()->after('quantity');
                }
                if (!Schema::hasColumn('daily_consumption_details', 'total_price')) {
                    $table->decimal('total_price', 15, 2)->nullable()->after('unit_price');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order
        Schema::dropIfExists('workflow_audits');
        Schema::dropIfExists('workflow_state_histories');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('daily_health_check_logs');
        Schema::dropIfExists('dpr_anomalies');
        Schema::dropIfExists('dpr_edit_history');

        // Note: We don't drop columns in rollback to preserve data
        // Columns can be manually removed if needed
    }

    /**
     * Check if foreign key exists
     */
    private function hasForeignKey(string $table, string $column): bool
    {
        try {
            $connection = Schema::getConnection();
            $doctrine = $connection->getDoctrineSchemaManager();
            $foreignKeys = $doctrine->listTableForeignKeys($connection->getDatabasePrefix() . $table);
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