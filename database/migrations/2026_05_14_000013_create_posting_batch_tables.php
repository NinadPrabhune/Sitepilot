<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create posting_batches and posting_failures tables for batch posting
 * Risk Level: LOW - Table creation only
 *
 * CRITICAL CONTEXT:
 * - Batch posting system for financial entries
 * - Tracks groups of postings processed together
 * - Failure table captures individual item failures within a batch
 *
 * SAFETY CHECKS:
 * - hasTable() guards for both tables
 * - No data changes
 * - FKs reference financial_postings and users
 *
 * Operation Order Rationale:
 * - Depends on: financial_postings table
 * - posting_batches: parent table for batch grouping
 * - posting_failures: child table tracking per-item failures
 * - Create in same migration to maintain atomicity
 *
 * Production Risks:
 * - Low - batch posting is backend process, not user-facing yet
 *
 * Rollback Safety:
 * - Drops both tables (created together)
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation (financial posting infrastructure)
 * - Prerequisite: financial_postings must exist
 * - Used by async posting jobs
 */
return new class extends Migration
{
    public function up(): void
    {
        // posting_batches - tracks batch execution metadata
        if (!Schema::hasTable('posting_batches')) {
            Schema::create('posting_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id', 50)->unique()->comment('Batch reference like BP-20260514-001');
                $table->enum('batch_type', ['journal', 'payment', 'adjustment', 'reversal'])->comment('Type of postings in batch');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'partial'])->default('pending');
                $table->unsignedBigInteger('initiated_by');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                // Counts
                $table->integer('total_items')->default(0);
                $table->integer('success_count')->default(0);
                $table->integer('failure_count')->default(0);

                // Totals
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->json('summary_data')->nullable();

                // Error capture for batch-level failures
                $table->text('error_message')->nullable();
                $table->json('error_context')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['batch_type', 'status'], 'idx_type_status');
                $table->index(['initiated_by', 'started_at'], 'idx_initiator_timeline');
                $table->index('started_at', 'idx_started_at');
                $table->index('completed_at', 'idx_completed_at');
                $table->index('status', 'idx_status');

                // Foreign keys
                $table->foreign('initiated_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict');
            });
        }

        // posting_failures - tracks individual item failures within batches
        if (!Schema::hasTable('posting_failures')) {
            Schema::create('posting_failures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id')->comment('References posting_batches.id');
                $table->string('entity_type', 30)->comment('Type of entity that failed');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('payload')->nullable()->comment('Original data that failed');
                $table->text('error_message');
                $table->string('error_code', 50)->nullable();
                $table->json('error_details')->nullable();
                $table->integer('retry_count')->default(0);
                $table->timestamp('next_retry_at')->nullable();
                $table->enum('status', ['failed', 'retrying', 'skipped'])->default('failed');
                $table->timestamps();

                // Indexes
                $table->index(['batch_id', 'status'], 'idx_batch_status');
                $table->index(['entity_type', 'entity_id'], 'idx_entity_lookup');
                $table->index('retry_count', 'idx_retry_count');
                $table->index('next_retry_at', 'idx_next_retry');
                $table->index('status', 'idx_status');

                // Foreign keys
                $table->foreign('batch_id')
                    ->references('id')
                    ->on('posting_batches')
                    ->onDelete('cascade'); // Clean up if batch deleted
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_failures');
        Schema::dropIfExists('posting_batches');
    }
};
