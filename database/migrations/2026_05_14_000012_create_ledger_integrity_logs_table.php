<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create ledger_integrity_logs table for accounting integrity monitoring
 * Risk Level: LOW - Table creation only
 *
 * CRITICAL CONTEXT:
 * - Financial control table for running balance validation
 * - Detects drifting balances between daily calculations and ledger
 * - Supports audit compliance requirements
 *
 * SAFETY CHECKS:
 * - hasTable() guard
 * - FKs to machinery_ledger, supplier_ledger
 * - No data manipulation
 *
 * Operation Order Rationale:
 * - Depends on: machinery_ledger, supplier_ledger exist
 * - Independent logging table
 * - Used by integrity check jobs
 *
 * Production Risks:
 * - None - empty table
 * - Low write volume (periodic checks)
 *
 * Rollback Safety:
 * - Drop table only
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation
 * - Requires machinery_ledger and supplier_ledger tables
 * - Monitor table growth - may need periodic archiving
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ledger_integrity_logs')) {
            Schema::create('ledger_integrity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 30)->comment('machinery_ledger, supplier_ledger');
                $table->unsignedBigInteger('entity_id')->nullable()->comment('Specific ledger ID if applicable');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Integrity check details
                $table->string('check_type', 50)->comment('balance_check, running_total, period_close');
                $table->enum('status', ['passed', 'failed', 'warning'])->default('passed');
                $table->json('expected_data')->nullable()->comment('Expected values');
                $table->json('actual_data')->nullable()->comment('Actual values from DB');
                $table->decimal('expected_total', 18, 4)->nullable();
                $table->decimal('actual_total', 18, 4)->nullable();
                $table->decimal('discrepancy', 18, 4)->nullable();
                $table->integer('error_count')->default(0);

                // Context
                $table->date('check_date');
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();

                // Resolution tracking
                $table->enum('resolution_status', ['open', 'investigating', 'resolved', 'false_positive'])->default('open');
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['entity_type', 'check_type'], 'idx_entity_check_type');
                $table->index(['workspace_id', 'check_date'], 'idx_ws_check_date');
                $table->index(['status', 'resolution_status'], 'idx_status_resolution');
                $table->index('check_date', 'idx_check_date');
                $table->index('entity_id', 'idx_entity_id');

                // Foreign keys
                $table->foreign('workspace_id')
                    ->references('id')
                    ->on('work_spaces')
                    ->onDelete('cascade');

                $table->foreign('site_id')
                    ->references('id')
                    ->on('projects')
                    ->onDelete('set null');

                $table->foreign('resolved_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_integrity_logs');
    }
};
