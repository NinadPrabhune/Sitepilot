<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create transaction_integrity_logs table for ACID compliance monitoring
 * Risk Level: LOW - Table creation only
 *
 * CONTEXT:
 * - Monitors database transaction health
 * - Detects deadlocks, rollbacks, lock timeouts
 * - Helps diagnose performance issues
 *
 * SAFETY:
 * - hasTable() guard
 * - No FKs (intentionally - logs even if referenced data gone)
 * - No data changes
 *
 * Operation Order:
 * - Independent - no dependencies
 * - Can be created at any time
 * - Minimal storage impact
 *
 * Production Risks:
 * - None - logging table
 *
 * Rollback Safety:
 * - dropIfExists
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation
 * - Monitoring/infrastructure table
 * - May need periodic cleanup (partitioning/archival)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transaction_integrity_logs')) {
            Schema::create('transaction_integrity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_id', 100)->comment('Unique transaction identifier');
                $table->string('connection_name', 50)->default('mysql')->comment('DB connection used');
                $table->enum('transaction_state', ['begin', 'commit', 'rollback', 'deadlock', 'timeout'])->comment('Final state');
                $table->enum('isolation_level', ['read_uncommitted', 'read_committed', 'repeatable_read', 'serializable'])->nullable();
                $table->boolean('had_deadlock')->default(false);
                $table->integer('retry_count')->default(0);
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->decimal('duration_ms', 10, 2)->nullable()->comment('Transaction duration');

                // Error details (if failed)
                $table->string('error_code', 20)->nullable();
                $table->text('error_message')->nullable();
                $table->json('error_context')->nullable();

                // SQL context (sanitized)
                $table->text('sql_state')->nullable();
                $table->json('queries_snapshot')->nullable()->comment('Array of queries executed');

                // System context
                $table->string('hostname')->nullable();
                $table->string('process_id')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                // Indexes for monitoring
                $table->index('transaction_id', 'idx_transaction_id');
                $table->index(['transaction_state', 'started_at'], 'idx_state_timeline');
                $table->index('had_deadlock', 'idx_deadlock_flag');
                $table->index('retry_count', 'idx_retry_count');
                $table->index('started_at', 'idx_started_at');
                $table->index(['connection_name', 'isolation_level'], 'idx_connection_isolation');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_integrity_logs');
    }
};
