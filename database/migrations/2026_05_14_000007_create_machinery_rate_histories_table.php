<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create machinery_rate_histories table for historical rate tracking
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - hasTable() guard prevents duplicate creation
 * - FK to machineries (parent table exists)
 * - No data modifications
 *
 * Operation Order Rationale:
 * - Depends on: machineries, users
 * - Independent of billing/payment flows
 * - Create early in machinery module batch
 *
 * Production Risks:
 * - None - empty table, simple FK
 *
 * Rollback Safety:
 * - Simple drop
 *
 * Deployment Notes:
 * - Batch 2: Machinery Module
 * - After machinery tables exist
 * - Used for billing rate audit trail
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machinery_rate_histories')) {
            Schema::create('machinery_rate_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->decimal('rate_per_hour', 10, 2)->comment('Hourly billing rate');
                $table->decimal('diesel_rate', 10, 2)->nullable()->comment('Diesel rate at time period');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->text('change_reason')->nullable();
                $table->timestamps();

                // Unique constraint: one effective rate per machinery per start date
                $table->unique(['machinery_id', 'effective_from'], 'unique_machinery_effective');

                // Indexes for common queries
                $table->index(['machinery_id', 'effective_from', 'effective_to'], 'idx_machinery_effective_range');
                $table->index('effective_from', 'idx_effective_start');
                $table->index('effective_to', 'idx_effective_end');

                // Foreign keys
                $table->foreign('machinery_id')
                    ->references('id')
                    ->on('machineries')
                    ->onDelete('cascade'); // Remove history if machinery removed

                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_rate_histories');
    }
};
