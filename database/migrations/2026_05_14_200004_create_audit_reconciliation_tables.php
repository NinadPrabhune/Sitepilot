<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BATCH 4: AUDIT & RECONCILIATION TABLES - Phase 4
 * ============================================================================
 * PRIORITY: MEDIUM-HIGH
 *
 * MIGRATION: create_audit_reconciliation_tables.php
 * TIMESTAMP: 2026_05_14_200004
 *
 * PURPOSE: Create audit trail, reconciliation, and integrity monitoring tables
 *
 * TABLES CREATED:
 * 1. reconciliation_logs - DPR/ledger reconciliation tracking
 * 2. payment_audit_logs - Payment activity auditing
 * 3. payment_health_logs - Payment system health monitoring
 * 4. payment_calculation_snapshots - Calculation result snapshots
 * 5. payment_request_status_logs - Payment request state transitions
 * 6. payment_request_histories - Payment request change history
 * 7. payment_reversals - Payment reversal records
 * 8. ledger_integrity_logs - Ledger balance integrity tracking
 * 9. transaction_integrity_logs - Transaction integrity checks
 * 10. invariant_logs - System invariant violations
 * 11. destructive_command_attempts - Security audit for dangerous operations
 * 12. item_categories - Item categorization
 * 13. items - Item master
 * 14. supplier_advances - Supplier advance tracking
 * 15. escalation_requests - Escalation request tracking
 * 16. financial_escalations - Financial escalation records
 * 17. journal_adjustments - Manual journal adjustments
 * 18. material_consumption_audits - Material consumption audit
 * 19. material_consumption_versions - Consumption versioning
 * 20. usage_calculation_logs - Usage calculation logging
 *
 * SAFETY RATIONALE:
 * - All tables use hasTable() guards for idempotency
 * - Pure audit/logging tables - no data modification
 * - Read-heavy, low write volume expected
 * - Nullable fields with safe defaults
 *
 * OPERATION ORDER:
 * - Can run in parallel with other batches
 * - Depends on machinery_ledger, supplier_ledger existing (created in Batch 1-2)
 *
 * PRODUCTION RISK: LOW
 * - Creates new tables only
 * - No impact on existing production data
 * - Low write volume (logging/audit tables)
 *
 * ROLLBACK: Each table can be dropped individually
 *
 * DEPLOYMENT NOTES:
 * - Run after Batch 1-3 complete
 * - Monitor disk space - audit tables grow over time
 * - Consider partitioning for large audit tables in production
 * - Implement archiving policy for historical logs
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. RECONCILIATION_LOGS
        // =====================================================================
        if (!Schema::hasTable('reconciliation_logs')) {
            Schema::create('reconciliation_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50)->comment('dpr, ledger, payment, dpr_summary');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Reconciliation details
                $table->string('reconciliation_type', 50)->comment('daily, weekly, monthly, manual');
                $table->enum('status', ['pending', 'passed', 'failed', 'warning', 'skipped'])->default('pending');
                $table->string('check_type', 100)->nullable()->comment('balance, count, hash, reference');

                // Data comparison
                $table->decimal('expected_value', 18, 4)->nullable();
                $table->decimal('actual_value', 18, 4)->nullable();
                $table->decimal('variance', 18, 4)->nullable();
                $table->decimal('variance_percent', 8, 4)->nullable();

                // Details
                $table->text('message')->nullable();
                $table->json('details')->nullable();
                $table->json('metadata')->nullable();

                // Resolution
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->enum('resolution_status', ['open', 'investigating', 'resolved', 'ignored', 'escalated'])
                    ->default('open');

                $table->timestamps();

                // Indexes
                $table->index(['entity_type', 'entity_id', 'created_at'], 'idx_recon_entity_date');
                $table->index(['workspace_id', 'status', 'created_at'], 'idx_recon_ws_status');
                $table->index(['reconciliation_type', 'status'], 'idx_recon_type_status');
                $table->index('resolved_by', 'idx_recon_resolved_by');
            });
        }

        // =====================================================================
        // 2. PAYMENT_AUDIT_LOGS
        // =====================================================================
        if (!Schema::hasTable('payment_audit_logs')) {
            Schema::create('payment_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('payment_type', 50)->comment('machinery, supplier, regular');
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('workspace_id');

                // Audit details
                $table->string('action', 50)->comment('created, updated, status_change, paid');
                $table->string('field_changed', 100)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();

                // Actor
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_type', 50)->nullable()->comment('user, system, api');

                $table->timestamps();

                // Indexes
                $table->index(['payment_type', 'payment_id', 'created_at'], 'idx_payment_audit');
                $table->index(['actor_id', 'created_at'], 'idx_payment_audit_actor');
            });
        }

        // =====================================================================
        // 3. PAYMENT_HEALTH_LOGS
        // =====================================================================
        if (!Schema::hasTable('payment_health_logs')) {
            Schema::create('payment_health_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('health_check_type', 50);

                // Status
                $table->enum('status', ['healthy', 'warning', 'critical', 'unknown'])->default('healthy');
                $table->text('message')->nullable();
                $table->json('metrics')->nullable();

                // Details
                $table->integer('records_checked')->default(0);
                $table->integer('issues_detected')->default(0);
                $table->decimal('execution_time_ms', 10, 2)->nullable();

                $table->timestamp('check_at');
                $table->timestamps();

                // Indexes
                $table->index(['workspace_id', 'check_at'], 'idx_health_date');
                $table->index(['health_check_type', 'status'], 'idx_health_type_status');
            });
        }

        // =====================================================================
        // 4. PAYMENT_CALCULATION_SNAPSHOTS
        // =====================================================================
        if (!Schema::hasTable('payment_calculation_snapshots')) {
            Schema::create('payment_calculation_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('calculable_type', 100)->comment('DailyProgressReport, etc');
                $table->unsignedBigInteger('calculable_id');
                $table->unsignedBigInteger('workspace_id');

                // Calculation details
                $table->integer('version_number')->default(1);
                $table->string('calculation_method', 50)->nullable();
                $table->decimal('calculated_amount', 15, 2)->default(0);

                // Input/output snapshot
                $table->json('input_parameters')->nullable();
                $table->json('calculation_details')->nullable();
                $table->decimal('execution_time_ms', 10, 2)->nullable();

                // Integrity
                $table->string('calculation_hash', 64)->nullable();
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_current')->default(true);

                // Audit
                $table->unsignedBigInteger('calculated_by')->nullable();
                $table->timestamp('calculated_at');

                $table->timestamps();

                // Indexes
                $table->index(['calculable_type', 'calculable_id', 'is_current'], 'idx_calc_snapshot_current');
                $table->index(['workspace_id', 'calculated_at'], 'idx_calc_snapshot_date');
            });
        }

        // =====================================================================
        // 5. PAYMENT_REQUEST_STATUS_LOGS
        // =====================================================================
        if (!Schema::hasTable('payment_request_status_logs')) {
            Schema::create('payment_request_status_logs', function (Blueprint $table) {
                $table->id();
                $table->string('request_type', 50)->comment('machinery, supplier');
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('workspace_id');

                // Transition details
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50);
                $table->string('transition_type', 50)->nullable()->comment('manual, automatic, system');

                // Context
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();

                // Actor
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_type', 50)->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['request_type', 'request_id', 'created_at'], 'idx_status_log_request');
                $table->index(['actor_id', 'created_at'], 'idx_status_log_actor');
            });
        }

        // =====================================================================
        // 6. PAYMENT_REQUEST_HISTORIES
        // =====================================================================
        if (!Schema::hasTable('payment_request_histories')) {
            Schema::create('payment_request_histories', function (Blueprint $table) {
                $table->id();
                $table->string('request_type', 50);
                $table->unsignedBigInteger('request_id');
                $table->unsignedBigInteger('workspace_id');

                // Snapshot data
                $table->json('snapshot_data')->nullable();
                $table->string('change_type', 50)->comment('create, update, status_change, delete');

                // Actor
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_ip', 50)->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['request_type', 'request_id'], 'idx_history_request');
                $table->index(['workspace_id', 'created_at'], 'idx_history_ws_date');
            });
        }

        // =====================================================================
        // 7. PAYMENT_REVERSALS
        // =====================================================================
        if (!Schema::hasTable('payment_reversals')) {
            Schema::create('payment_reversals', function (Blueprint $table) {
                $table->id();
                $table->string('payment_type', 50)->comment('machinery, supplier, regular');
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('workspace_id');

                // Reversal details
                $table->decimal('original_amount', 15, 2);
                $table->decimal('reversal_amount', 15, 2);
                $table->enum('status', ['pending', 'approved', 'processed', 'rejected', 'cancelled'])
                    ->default('pending');

                // Reason
                $table->string('reversal_reason', 100);
                $table->text('reversal_notes')->nullable();
                $table->json('reversal_data')->nullable();

                // Processing
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processed_at')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['payment_type', 'payment_id'], 'idx_reversal_payment');
                $table->index(['status', 'created_at'], 'idx_reversal_status');
            });
        }

        // =====================================================================
        // 8. LEDGER_INTEGRITY_LOGS (if not exists)
        // =====================================================================
        if (!Schema::hasTable('ledger_integrity_logs')) {
            Schema::create('ledger_integrity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 30)->comment('machinery_ledger, supplier_ledger');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Check details
                $table->string('check_type', 50)->comment('balance_check, running_total, period_close');
                $table->enum('status', ['passed', 'failed', 'warning'])->default('passed');
                $table->json('expected_data')->nullable();
                $table->json('actual_data')->nullable();
                $table->decimal('expected_total', 18, 4)->nullable();
                $table->decimal('actual_total', 18, 4)->nullable();
                $table->decimal('discrepancy', 18, 4)->nullable();
                $table->integer('error_count')->default(0);

                // Context
                $table->date('check_date');
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();

                // Resolution
                $table->enum('resolution_status', ['open', 'investigating', 'resolved', 'false_positive'])
                    ->default('open');
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['entity_type', 'check_type'], 'idx_integrity_type');
                $table->index(['workspace_id', 'check_date'], 'idx_integrity_ws_date');
                $table->index(['status', 'resolution_status'], 'idx_integrity_status');
                $table->index('check_date', 'idx_integrity_date');
            });
        }

        // =====================================================================
        // 9. TRANSACTION_INTEGRITY_LOGS
        // =====================================================================
        if (!Schema::hasTable('transaction_integrity_logs')) {
            Schema::create('transaction_integrity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_type', 50);
                $table->unsignedBigInteger('transaction_id');
                $table->unsignedBigInteger('workspace_id');

                // Check details
                $table->string('integrity_check', 100);
                $table->enum('status', ['passed', 'failed', 'warning'])->default('passed');
                $table->text('message')->nullable();
                $table->json('details')->nullable();

                $table->timestamp('checked_at');
                $table->timestamps();

                // Indexes
                $table->index(['transaction_type', 'transaction_id'], 'idx_tx_integrity');
                $table->index(['status', 'checked_at'], 'idx_tx_integrity_status');
            });
        }

        // =====================================================================
        // 10. INVARIANT_LOGS
        // =====================================================================
        if (!Schema::hasTable('invariant_logs')) {
            Schema::create('invariant_logs', function (Blueprint $table) {
                $table->id();
                $table->string('invariant_name', 100);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->unsignedBigInteger('workspace_id');

                // Violation details
                $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('warning');
                $table->text('violation_message');
                $table->json('violation_data')->nullable();

                // Context
                $table->string('context_type', 50)->nullable();
                $table->unsignedBigInteger('context_id')->nullable();
                $table->json('stack_context')->nullable();

                $table->timestamp('violated_at');
                $table->timestamps();

                // Indexes
                $table->index(['invariant_name', 'severity'], 'idx_invariant_severity');
                $table->index(['workspace_id', 'violated_at'], 'idx_invariant_date');
            });
        }

        // =====================================================================
        // 11. DESTRUCTIVE_COMMAND_ATTEMPTS
        // =====================================================================
        if (!Schema::hasTable('destructive_command_attempts')) {
            Schema::create('destructive_command_attempts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('command_type', 50)->comment('drop_table, truncate, delete_all');
                $table->string('target_table', 100)->nullable();

                // Attempt details
                $table->text('command_details')->nullable();
                $table->json('attempted_conditions')->nullable();
                $table->enum('status', ['blocked', 'allowed', 'failed'])->default('blocked');
                $table->text('block_reason')->nullable();

                // Context
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('source', 50)->nullable()->comment('api, console, ui');

                $table->timestamp('attempted_at');
                $table->timestamps();

                // Indexes
                $table->index(['user_id', 'attempted_at'], 'idx_destructive_user');
                $table->index(['command_type', 'status'], 'idx_destructive_type');
            });
        }

        // =====================================================================
        // 12. ITEM_CATEGORIES
        // =====================================================================
        if (!Schema::hasTable('item_categories')) {
            Schema::create('item_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('name', 100);
                $table->string('code', 50)->nullable()->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);

                $table->timestamps();

                // Indexes
                $table->index('parent_id', 'idx_category_parent');
                $table->index('is_active', 'idx_category_active');
            });
        }

        // =====================================================================
        // 13. ITEMS
        // =====================================================================
        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('workspace_id');

                // Item details
                $table->string('name', 100);
                $table->string('code', 50)->nullable()->unique();
                $table->string('unit', 20)->nullable();
                $table->text('description')->nullable();

                // Pricing
                $table->decimal('unit_price', 15, 2)->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['category_id', 'is_active'], 'idx_item_category');
                $table->index(['workspace_id', 'is_active'], 'idx_item_workspace');
            });
        }

        // =====================================================================
        // 14. SUPPLIER_ADVANCES
        // =====================================================================
        if (!Schema::hasTable('supplier_advances')) {
            Schema::create('supplier_advances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Advance details
                $table->decimal('advance_amount', 15, 2);
                $table->decimal('adjusted_amount', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2)->default(0);
                $table->date('advance_date');

                // Status
                $table->enum('status', ['active', 'partially_used', 'fully_used', 'cancelled'])->default('active');

                // Reference
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('remarks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['supplier_id', 'status'], 'idx_advance_supplier_status');
                $table->index(['workspace_id', 'advance_date'], 'idx_advance_date');
            });
        }

        // =====================================================================
        // 15. ESCALATION_REQUESTS
        // =====================================================================
        if (!Schema::hasTable('escalation_requests')) {
            Schema::create('escalation_requests', function (Blueprint $table) {
                $table->id();
                $table->string('escalation_type', 50)->comment('approval, payment, dispute');
                $table->unsignedBigInteger('reference_id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Escalation details
                $table->string('priority', 20)->default('medium');
                $table->text('reason');
                $table->enum('status', ['pending', 'approved', 'rejected', 'escalated'])->default('pending');

                // Actor
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('escalated_to')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['escalation_type', 'status'], 'idx_escalation_type');
                $table->index(['workspace_id', 'created_at'], 'idx_escalation_date');
            });
        }

        // =====================================================================
        // 16. FINANCIAL_ESCALATIONS
        // =====================================================================
        if (!Schema::hasTable('financial_escalations')) {
            Schema::create('financial_escalations', function (Blueprint $table) {
                $table->id();
                $table->string('escalation_type', 50)->comment('payment_limit, approval_threshold, reconciliation');
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('workspace_id');

                // Amount details
                $table->decimal('amount_involved', 15, 2)->nullable();
                $table->decimal('escalation_threshold', 15, 2)->nullable();

                // Details
                $table->text('description');
                $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('high');
                $table->enum('status', ['open', 'under_review', 'resolved', 'dismissed'])->default('open');

                // Resolution
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['status', 'priority'], 'idx_fin_escalation_priority');
                $table->index(['workspace_id', 'created_at'], 'idx_fin_escalation_date');
            });
        }

        // =====================================================================
        // 17. JOURNAL_ADJUSTMENTS
        // =====================================================================
        if (!Schema::hasTable('journal_adjustments')) {
            Schema::create('journal_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('voucher_number', 50)->nullable()->unique();

                // Entry details
                $table->date('adjustment_date');
                $table->string('adjustment_type', 50)->comment('correction, rounding, reversal');
                $table->text('description');

                // Amount
                $table->decimal('debit_total', 15, 2)->default(0);
                $table->decimal('credit_total', 15, 2)->default(0);

                // Status
                $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();

                // Audit
                $table->unsignedBigInteger('created_by');
                $table->text('reason')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['workspace_id', 'adjustment_date'], 'idx_journal_date');
                $table->index('voucher_number', 'idx_journal_voucher');
            });
        }

        // =====================================================================
        // 18. MATERIAL_CONSUMPTION_AUDITS
        // =====================================================================
        if (!Schema::hasTable('material_consumption_audits')) {
            Schema::create('material_consumption_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('daily_consumption_master_id');
                $table->unsignedBigInteger('workspace_id');

                // Audit details
                $table->string('audit_type', 50)->comment('variance, quantity_check, rate_approval');
                $table->enum('status', ['pending', 'approved', 'rejected', 'varied'])->default('pending');
                $table->text('description');

                // Comparison
                $table->decimal('expected_quantity', 15, 4)->nullable();
                $table->decimal('actual_quantity', 15, 4)->nullable();
                $table->decimal('variance', 15, 4)->nullable();
                $table->decimal('variance_percent', 8, 4)->nullable();

                // Resolution
                $table->text('resolution')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['daily_consumption_master_id', 'created_at'], 'idx_consumption_audit');
            });
        }

        // =====================================================================
        // 19. MATERIAL_CONSUMPTION_VERSIONS
        // =====================================================================
        if (!Schema::hasTable('material_consumption_versions')) {
            Schema::create('material_consumption_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consumption_id');
                $table->unsignedBigInteger('workspace_id');

                // Version details
                $table->integer('version_number')->default(1);
                $table->json('consumption_data');
                $table->string('change_type', 50)->comment('initial, update, correction');

                // Audit
                $table->unsignedBigInteger('modified_by');
                $table->text('change_reason')->nullable();
                $table->timestamp('modified_at');

                // Indexes
                $table->index(['consumption_id', 'version_number'], 'idx_consumption_version');
            });
        }

        // =====================================================================
        // 20. USAGE_CALCULATION_LOGS
        // =====================================================================
        if (!Schema::hasTable('usage_calculation_logs')) {
            Schema::create('usage_calculation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('daily_progress_report_id')->nullable();

                // Calculation details
                $table->date('calculation_date');
                $table->decimal('total_hours', 10, 2)->default(0);
                $table->decimal('idle_hours', 10, 2)->default(0);
                $table->decimal('diesel_consumed', 10, 2)->default(0);
                $table->decimal('calculated_amount', 15, 2)->default(0);

                // Method
                $table->string('calculation_method', 50)->nullable();
                $table->json('rate_snapshot')->nullable();
                $table->string('calculation_hash', 64)->nullable();

                // Status
                $table->enum('status', ['calculated', 'verified', 'approved', 'rejected'])->default('calculated');
                $table->text('notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['machinery_id', 'calculation_date'], 'idx_usage_calc_date');
                $table->index(['workspace_id', 'status'], 'idx_usage_calc_status');
            });
        }

        // =====================================================================
        // 21. ASSETS_TOOLS_AND_EQUIPMENT_TRANSFER
        // =====================================================================
        if (!Schema::hasTable('assets_tools_and_equipment_transfer')) {
            Schema::create('assets_tools_and_equipment_transfer', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->unsignedBigInteger('from_site_id');
                $table->unsignedBigInteger('to_site_id');
                $table->unsignedBigInteger('workspace_id');

                // Transfer details
                $table->date('transfer_date');
                $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
                $table->string('transfer_type', 50)->nullable()->comment('permanent, temporary, assignment');

                // Quantities
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('returned_quantity', 10, 2)->nullable();

                // Reason and notes
                $table->string('reason', 100)->nullable();
                $table->text('remarks')->nullable();

                // Audit
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['asset_id', 'status'], 'idx_transfer_asset');
                $table->index(['from_site_id', 'transfer_date'], 'idx_transfer_from');
                $table->index(['to_site_id', 'transfer_date'], 'idx_transfer_to');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assets_tools_and_equipment_transfer');
        Schema::dropIfExists('usage_calculation_logs');
        Schema::dropIfExists('material_consumption_versions');
        Schema::dropIfExists('material_consumption_audits');
        Schema::dropIfExists('journal_adjustments');
        Schema::dropIfExists('financial_escalations');
        Schema::dropIfExists('escalation_requests');
        Schema::dropIfExists('supplier_advances');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('destructive_command_attempts');
        Schema::dropIfExists('invariant_logs');
        Schema::dropIfExists('transaction_integrity_logs');
        Schema::dropIfExists('ledger_integrity_logs');
        Schema::dropIfExists('payment_reversals');
        Schema::dropIfExists('payment_request_histories');
        Schema::dropIfExists('payment_request_status_logs');
        Schema::dropIfExists('payment_calculation_snapshots');
        Schema::dropIfExists('payment_health_logs');
        Schema::dropIfExists('payment_audit_logs');
        Schema::dropIfExists('reconciliation_logs');
    }
};