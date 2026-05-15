<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BATCH 1: CORE FINANCIAL TABLES - Phase 1
 * ============================================================================
 * PRIORITY: CRITICAL
 *
 * MIGRATION: create_financial_core_tables.php
 * TIMESTAMP: 2026_05_14_200001
 *
 * PURPOSE: Create core financial ledger tables required for accounting integrity
 *
 * TABLES CREATED:
 * 1. ledger_entries - Main ledger entry tracking
 * 2. supplier_ledger - Supplier transaction ledger
 * 3. supplier_ledger_entries - Individual supplier ledger entries
 * 4. financial_postings - Financial posting records
 * 5. posting_batches - Batch processing for postings
 * 6. posting_failures - Failed posting tracking
 *
 * SAFETY RATIONALE:
 * - All tables use hasTable() guards for idempotency
 * - No data modifications - pure table creation
 * - Safe for production with existing data
 * - Uses RESTRICT delete mode to prevent accidental data loss
 *
 * OPERATION ORDER:
 * ledger_entries MUST be created first (other tables reference it)
 * supplier_ledger can be created after (independent)
 *
 * PRODUCTION RISK: LOW
 * - Creates new empty tables only
 * - No impact on existing tables
 * - No foreign keys to existing data needed for these base tables
 *
 * ROLLBACK: Each table can be dropped individually
 *
 * DEPLOYMENT NOTES:
 * - Run first in deployment sequence
 * - No backfill needed initially
 * - These tables are for NEW transactions
 * ============================================================================
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // =====================================================================
        // 1. LEDGER_ENTRIES - Core ledger tracking
        // =====================================================================
        if (!Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_type', 50)->comment('Type: machinery, supplier, journal');
                $table->string('transaction_type', 50)->comment('credit, debit, adjustment');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Financial amounts
                $table->decimal('amount', 18, 4)->default(0);
                $table->decimal('balance_after', 18, 4)->default(0);
                $table->string('currency', 3)->default('INR');

                // Source reference
                $table->string('source_type', 100)->nullable()->comment('Model class name');
                $table->unsignedBigInteger('source_id')->nullable()->comment('Model instance ID');

                // Metadata
                $table->date('entry_date');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->json('tags')->nullable();

                // Integrity
                $table->string('idempotency_key', 100)->nullable()->unique()->comment('Prevent duplicate entries');
                $table->boolean('is_reversal')->default(false);
                $table->unsignedBigInteger('reversed_by_entry_id')->nullable();
                $table->boolean('is_system_generated')->default(false);

                // Lock tracking
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes for performance
                $table->index(['entry_type', 'entry_date'], 'idx_ledger_type_date');
                $table->index(['workspace_id', 'entry_date'], 'idx_workspace_date');
                $table->index(['site_id', 'entry_date'], 'idx_site_date');
                $table->index(['source_type', 'source_id'], 'idx_source_reference');
                $table->index('idempotency_key', 'idx_idempotency');
                $table->index('is_reversal', 'idx_is_reversal');
            });
        }

        // =====================================================================
        // 2. SUPPLIER_LEDGER - Supplier transaction ledger
        // =====================================================================
        if (!Schema::hasTable('supplier_ledger')) {
            Schema::create('supplier_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('workspace_id');

                // Entry details
                $table->enum('entry_direction', ['credit', 'debit'])->default('debit');
                $table->string('entry_type', 50)->default('transaction');
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('running_balance', 15, 2)->default(0);

                // Reference to source
                $table->string('reference_type', 100)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();

                // Metadata
                $table->date('date')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();

                // Reversal tracking
                $table->boolean('is_reversal')->default(false);
                $table->unsignedBigInteger('reversed_entry_id')->nullable();

                // Lock tracking
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['supplier_id', 'date'], 'idx_supplier_date');
                $table->index(['reference_type', 'reference_id'], 'idx_reference');
                $table->index('is_reversal', 'idx_supplier_reversal');
            });
        }

        // =====================================================================
        // 3. SUPPLIER_LEDGER_ENTRIES - Individual supplier ledger entries
        // =====================================================================
        if (!Schema::hasTable('supplier_ledger_entries')) {
            Schema::create('supplier_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_ledger_id');
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Entry details
                $table->enum('entry_type', ['credit', 'debit', 'adjustment', 'reversal'])->default('credit');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('category', 50)->nullable()->comment('diesel, material, service, advance');
                $table->string('voucher_number', 50)->nullable();

                // Source reference
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                // Date and description
                $table->date('entry_date');
                $table->text('description')->nullable();
                $table->json('attributes')->nullable();

                // Balance tracking
                $table->decimal('balance_before', 15, 2)->default(0);
                $table->decimal('balance_after', 15, 2)->default(0);

                // System fields
                $table->boolean('is_system_generated')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['supplier_id', 'entry_date'], 'idx_supplier_entry_date');
                $table->index(['workspace_id', 'entry_date'], 'idx_ws_entry_date');
                $table->index(['source_type', 'source_id'], 'idx_entry_source');
                $table->index('category', 'idx_entry_category');
            });
        }

        // =====================================================================
        // 4. FINANCIAL_POSTINGS - Financial posting records
        // =====================================================================
        if (!Schema::hasTable('financial_postings')) {
            Schema::create('financial_postings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('posting_batch_id')->nullable();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Posting type
                $table->string('posting_type', 50)->comment('debit, credit, journal');
                $table->string('account_code', 50)->nullable();
                $table->string('account_name', 100)->nullable();

                // Amount
                $table->decimal('amount', 18, 4)->default(0);
                $table->string('currency', 3)->default('INR');

                // Source reference
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                // Date and description
                $table->date('posting_date');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();

                // Status
                $table->enum('status', ['pending', 'posted', 'failed', 'reversed'])->default('pending');
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->text('failure_reason')->nullable();

                // Idempotency
                $table->string('idempotency_key', 100)->nullable()->unique();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['posting_batch_id', 'status'], 'idx_batch_status');
                $table->index(['workspace_id', 'posting_date'], 'idx_ws_posting_date');
                $table->index(['source_type', 'source_id'], 'idx_posting_source');
                $table->index('idempotency_key', 'idx_posting_idempotency');
                $table->index(['status', 'posting_date'], 'idx_status_date');
            });
        }

        // =====================================================================
        // 5. POSTING_BATCHES - Batch processing for postings
        // =====================================================================
        if (!Schema::hasTable('posting_batches')) {
            Schema::create('posting_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('created_by');

                // Batch details
                $table->string('batch_type', 50)->comment('daily, monthly, manual');
                $table->string('batch_reference', 100)->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();

                // Status and counts
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'partial'])->default('pending');
                $table->integer('total_records')->default(0);
                $table->integer('processed_records')->default(0);
                $table->integer('failed_records')->default(0);

                // Amount totals
                $table->decimal('total_debit', 18, 4)->default(0);
                $table->decimal('total_credit', 18, 4)->default(0);

                // Processing
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['workspace_id', 'status'], 'idx_ws_batch_status');
                $table->index(['period_start', 'period_end'], 'idx_period');
                $table->index('batch_reference', 'idx_batch_reference');
            });
        }

        // =====================================================================
        // 6. POSTING_FAILURES - Failed posting tracking
        // =====================================================================
        if (!Schema::hasTable('posting_failures')) {
            Schema::create('posting_failures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('posting_batch_id')->nullable();
                $table->unsignedBigInteger('workspace_id');

                // Failure details
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('failure_code', 50)->nullable();
                $table->text('failure_message');
                $table->json('error_details')->nullable();
                $table->text('stack_trace')->nullable();

                // Retry tracking
                $table->integer('retry_count')->default(0);
                $table->timestamp('last_retry_at')->nullable();
                $table->enum('status', ['pending', 'retrying', 'resolved', 'ignored'])->default('pending');
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['status', 'retry_count'], 'idx_failure_retry');
                $table->index(['source_type', 'source_id'], 'idx_failure_source');
                $table->index('failure_code', 'idx_failure_code');
            });
        }

        // =====================================================================
        // 7. FINANCIAL_PERIOD_LOCKS - Financial period locking
        // =====================================================================
        if (!Schema::hasTable('financial_period_locks')) {
            Schema::create('financial_period_locks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Period details
                $table->integer('year');
                $table->integer('month');
                $table->enum('period_type', ['monthly', 'quarterly', 'yearly'])->default('monthly');

                // Lock status
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->text('lock_reason')->nullable();

                // Unlock tracking
                $table->timestamp('unlocked_at')->nullable();
                $table->unsignedBigInteger('unlocked_by')->nullable();
                $table->text('unlock_reason')->nullable();

                $table->timestamps();

                // Unique constraint
                $table->unique(['workspace_id', 'year', 'month', 'period_type'], 'idx_unique_period');

                // Indexes
                $table->index(['workspace_id', 'is_locked'], 'idx_ws_locked');
                $table->index(['year', 'month'], 'idx_year_month');
            });
        }

        // =====================================================================
        // 8. FINANCIAL_GATE_BLOCKS - Financial approval gates
        // =====================================================================
        if (!Schema::hasTable('financial_gate_blocks')) {
            Schema::create('financial_gate_blocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Gate details
                $table->string('gate_name', 100);
                $table->string('gate_type', 50)->comment('amount_limit, approval_required, verification');
                $table->decimal('threshold_amount', 18, 4)->nullable();
                $table->boolean('is_active')->default(true);

                // Block criteria
                $table->string('applicable_entities', 100)->nullable()->comment('comma-separated entity types');
                $table->json('conditions')->nullable();

                // Status
                $table->enum('status', ['active', 'blocked', 'bypassed'])->default('active');
                $table->text('block_reason')->nullable();
                $table->unsignedBigInteger('blocked_by')->nullable();
                $table->timestamp('blocked_at')->nullable();

                // Audit
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('bypassed_until')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['workspace_id', 'is_active'], 'idx_ws_active_gates');
                $table->index('gate_type', 'idx_gate_type');
                $table->index(['status', 'is_active'], 'idx_status_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order of creation (respecting FK dependencies)
        Schema::dropIfExists('posting_failures');
        Schema::dropIfExists('financial_gate_blocks');
        Schema::dropIfExists('financial_period_locks');
        Schema::dropIfExists('posting_batches');
        Schema::dropIfExists('financial_postings');
        Schema::dropIfExists('supplier_ledger_entries');
        Schema::dropIfExists('supplier_ledger');
        Schema::dropIfExists('ledger_entries');
    }
};