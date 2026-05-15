<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create machinery_usage_logs table for raw usage reading tracking
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - Idempotent table creation
 * - FKs to machinery, users, projects
 * - No data modifications
 *
 * Operation Order Rationale:
 * - Depends on: machineries, sites (projects), users
 * - Raw operational log table
 * - Independent of DPR/billing tables
 *
 * Production Risks:
 * - None - log table, no queries dependent yet
 *
 * Rollback Safety:
 * - Simple drop
 *
 * Deployment Notes:
 * - Batch 2: Machinery Module
 * - Core table for machine operational history
 * - High volume table - consider partitioning later if needed
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machinery_usage_logs')) {
            Schema::create('machinery_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('machinery_id')->constrained()->onDelete('cascade');
                $table->foreignId('site_id')->nullable()->constrained('projects')->onDelete('set null');
                $table->foreignId('workspace_id')->constrained()->onDelete('cascade');

                // Machine readings
                $table->decimal('reading_start', 12, 2)->comment('ODO reading at start');
                $table->decimal('reading_end', 12, 2)->comment('ODO reading at end');
                $table->decimal('total_hours', 10, 2)->comment('Hours worked (reading_end - reading_start)');
                $table->decimal('idle_hours', 10, 2)->default(0)->comment('Idle/engine running but no work');
                $table->decimal('diesel_consumed', 10, 2)->default(0);

                // Who/when
                $table->date('usage_date');
                $table->foreignId('operator_id')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');

                // Additional context
                $table->string('source', 30)->default('manual')->comment('manual, activity, import');
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                // Indexes for query patterns
                $table->index(['machinery_id', 'usage_date'], 'idx_machinery_date');
                $table->index(['site_id', 'usage_date'], 'idx_site_date');
                $table->index(['workspace_id', 'usage_date'], 'idx_ws_date');
                $table->index('operator_id', 'idx_operator');
                $table->index(['machinery_id', 'reading_end'], 'idx_machinery_reading');

                // Unique constraint: one machinery entry per date (unless multiple shifts)
                // $table->unique(['machinery_id', 'usage_date', 'operator_id'], 'uniq_machinery_date_operator');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_usage_logs');
    }
};
