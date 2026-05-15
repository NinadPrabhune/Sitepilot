<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create usage_calculation_logs table for DPR calculation audit trail
 * Risk Level: LOW - Table creation only
 *
 * CONTEXT:
 * - Logs each DPR amount calculation (billable_hours * rate)
 * - Supports debugging of billing discrepancies
 * - Captures input values and final calculated amount
 *
 * SAFETY:
 * - hasTable() guard
 * - FK to daily_progress_reports
 * - No data modifications
 *
 * Operation Order:
 * - Depends on: daily_progress_reports
 * - Created after DPR table exists
 * - Child table, no further dependencies
 *
 * Production Risks:
 * - Low volume - one record per DPR calculation
 * - Write-heavy during billing runs but manageable
 *
 * Rollback Safety:
 * - Drops table
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation
 * - Critical for billing audit
 * - May want to add TTL/archival policy later
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usage_calculation_logs')) {
            Schema::create('usage_calculation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('daily_progress_report_id');
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');
                $table->date('report_date');

                // Input to calculation
                $table->decimal('machine_start_reading', 12, 2)->nullable();
                $table->decimal('machine_end_reading', 12, 2)->nullable();
                $table->decimal('machine_idle_reading', 12, 2)->nullable();
                $table->decimal('calculated_hours', 10, 2)->nullable();
                $table->decimal('billable_hours', 10, 2)->nullable();

                // Rate used
                $table->decimal('rate_per_hour', 10, 2)->nullable();
                $table->string('rate_source', 20)->nullable()->comment('machinery.rate, supplier_rate, override');
                $table->decimal('override_rate', 10, 2)->nullable();
                $table->unsignedBigInteger('override_by')->nullable();

                // Result
                $table->decimal('calculated_amount', 15, 2)->nullable();
                $table->string('currency', 3)->default('USD');

                // Calculation metadata
                $table->string('calculation_version', 20)->nullable()->comment('Rules version used');
                $table->string('calculation_hash', 64)->nullable()->comment('Deterministic hash of inputs');
                $table->json('calculation_metadata')->nullable()->comment('Additional context');

                // Approval state
                $table->enum('approval_status', ['pending', 'approved', 'rejected', 'adjusted'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();

                // Reversal tracking
                $table->boolean('is_reversed')->default(false);
                $table->unsignedBigInteger('reversed_entry_id')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['daily_progress_report_id'], 'idx_dpr_id');
                $table->index(['machinery_id', 'report_date'], 'idx_machinery_date');
                $table->index(['workspace_id', 'report_date'], 'idx_ws_date');
                $table->index('calculation_hash', 'idx_calc_hash');
                $table->index('approval_status', 'idx_approval_status');
                $table->index('is_reversed', 'idx_reversed');

                // Foreign keys
                $table->foreign('daily_progress_report_id')
                    ->references('id')
                    ->on('daily_progress_reports')
                    ->onDelete('cascade');

                $table->foreign('machinery_id')
                    ->references('id')
                    ->on('machineries')
                    ->onDelete('cascade');

                $table->foreign('workspace_id')
                    ->references('id')
                    ->on('work_spaces')
                    ->onDelete('cascade');

                $table->foreign('override_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->foreign('approved_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->foreign('reversed_entry_id')
                    ->references('id')
                    ->on('usage_calculation_logs')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_calculation_logs');
    }
};
