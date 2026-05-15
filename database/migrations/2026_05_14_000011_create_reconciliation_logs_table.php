<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create reconciliation_logs table for financial/data reconciliation audit
 * Risk Level: LOW - Table creation only
 *
 * CRITICAL CONTEXT:
 * - Core financial audit table
 * - Tracks reconciliation between DPR, ledgers, and payments
 * - Used for month-end closing and financial integrity checks
 *
 * SAFETY CHECKS:
 * - hasTable() guard ensures idempotency
 * - No data modifications
 * - FKs reference core tables only
 *
 * Operation Order Rationale:
 * - Depends on: daily_progress_reports, machinery_ledger, supplier_ledger, payment_requests
 * - Created AFTER those tables exist
 * - Independent - no other tables depend on this
 *
 * Production Risks:
 * - Minimal - empty table, indexes are fast to create
 *
 * Rollback Safety:
 * - Drops only the new table
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation
 * - Check that dependent tables exist before running
 * - Used by reconciliation service
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reconciliation_logs')) {
            Schema::create('reconciliation_logs', function (Blueprint $table) {
                $table->id();
                $table->string('reconciliation_id', 50)->unique()->comment('Human-readable reference');
                $table->enum('reconciliation_type', [
                    'dpr_to_ledger',
                    'ledger_to_payment',
                    'supplier_ledger',
                    'machinery_balance',
                    'payment_allocation'
                ])->comment('Type of reconciliation performed');

                // Date range being reconciled
                $table->date('period_start');
                $table->date('period_end');

                // Entity references (polymorphic-ish)
                $table->string('entity_type')->nullable()->comment(' machinery, supplier, workspace');
                $table->unsignedBigInteger('entity_id')->nullable();

                // Reconciliation outcome
                $table->enum('status', ['pending', 'in_progress', 'matched', 'mismatched', 'resolved'])->default('pending');
                $table->json('summary_data')->nullable()->comment('Summary of results');
                $table->decimal('total_expected', 15, 2)->nullable()->comment('Expected total');
                $table->decimal('total_actual', 15, 2)->nullable()->comment('Actual total');
                $table->decimal('discrepancy_amount', 15, 2)->nullable()->comment('Difference');
                $table->integer('mismatch_count')->default(0)->comment('Number of mismatched items');

                // Execution tracking
                $table->unsignedBigInteger('initiated_by');
                $table->timestamp('initiated_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();

                // Resolution tracking
                $table->text('resolution_notes')->nullable();
                $table->json('adjustment_entries')->nullable()->comment('JSON array of adjusting ledger entries');

                $table->timestamps();

                // Indexes for reconciliation queries
                $table->index(['reconciliation_type', 'status'], 'idx_type_status');
                $table->index(['entity_type', 'entity_id'], 'idx_entity_lookup');
                $table->index(['period_start', 'period_end'], 'idx_period_range');
                $table->index(['initiated_by', 'initiated_at'], 'idx_initiator_timeline');
                $table->index('completed_at', 'idx_completed_at');
                $table->index('status', 'idx_status');

                // Foreign keys
                $table->foreign('initiated_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict');

                $table->foreign('completed_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_logs');
    }
};
