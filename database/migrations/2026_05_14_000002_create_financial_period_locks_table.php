<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create financial_period_locks table for accounting period control
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - Schema::hasTable() guard ensures idempotency
 * - No destructive operations
 * - FK references to users table (assumed to exist)
 *
 * Operation Order Rationale:
 * - Financial period management depends on users table for closed_by FK
 * - Should be created before any period-locking logic is used
 * - Independent of other financial tables
 *
 * Production Risks:
 * - Table creation is atomic, no locking issues
 * - check constraint is ignored by MySQL but documented for strict mode
 *
 * Rollback Safety:
 * - Drops only the newly created table
 * - No cascading effects (no child FKs yet)
 *
 * Deployment Notes:
 * - Batch 1: Core financial infrastructure
 * - No data backfill needed
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_period_locks')) {
            Schema::create('financial_period_locks', function (Blueprint $table) {
                $table->id();
                $table->enum('period_type', ['month', 'quarter', 'year'])->comment('Period granularity');
                $table->date('period_start')->comment('Start date of period');
                $table->date('period_end')->comment('End date of period');
                $table->enum('status', ['open', 'closed', 'locked'])->default('open')->comment('Lock status');
                $table->unsignedBigInteger('closed_by')->nullable()->comment('User who locked period');
                $table->timestamp('closed_at')->nullable()->comment('When period was locked');
                $table->unsignedBigInteger('created_by')->comment('User who created record');
                $table->text('remarks')->nullable()->comment('Optional notes');
                $table->timestamps();

                // Unique constraint to prevent duplicate period definitions
                $table->unique(['period_type', 'period_start'], 'unique_period_definition');

                // Indexes for common queries
                $table->index('status', 'idx_period_status');
                $table->index(['period_start', 'period_end'], 'idx_period_date_range');
                $table->index('closed_by', 'idx_closed_by');

                // Foreign keys
                $table->foreign('closed_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict');

                // Data integrity constraint (informational for MySQL)
                // $table->check('period_end >= period_start');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_period_locks');
    }
};
