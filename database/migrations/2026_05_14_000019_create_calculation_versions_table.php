<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create calculation_versions table for rule versioning
 * Risk Level: LOW - Table creation only
 *
 * CRITICAL CONTEXT:
 * - Stores calculation rule versions (billing formulas, rate rules)
 * - Allows rollback to previous calculation logic
 * - Each DPR/ledger entry references a version for audit
 *
 * SAFETY:
 * - hasTable() guard
 * - Simple table, FKs to users
 *
 * Operation Order:
 * - Independent, minimal dependencies
 * - Core for billing reproducibility
 *
 * Production Risks:
 * - Low - seed with initial version after creation
 *
 * Rollback Safety:
 * - Drop table
 *
 * Deployment Notes:
 * - Batch 5: Performance & Versioning
 * - After creation, run seeder to insert initial version
 * - Used by billing calculator service
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('calculation_versions')) {
            Schema::create('calculation_versions', function (Blueprint $table) {
                $table->id();
                $table->string('version', 20)->unique()->comment('Semantic version like v1.2.3');
                $table->string('type', 50)->comment('dpr_calculation, billing, diesel');
                $table->text('description');
                $table->json('rules')->nullable()->comment('Serialized calculation rules/config');
                $table->boolean('is_active')->default(true)->comment('Active version used for new calculations');
                $table->timestamp('effective_from')->useCurrent();
                $table->timestamp('effective_to')->nullable()->comment('When this version was superseded');
                $table->unsignedBigInteger('created_by');
                $table->timestamp('deprecated_at')->nullable();
                $table->text('deprecation_reason')->nullable();

                $table->timestamps();

                // Indexes for version lookups
                $table->index(['type', 'is_active'], 'idx_type_active');
                $table->index(['type', 'effective_from'], 'idx_type_effective_from');
                $table->index(['effective_from', 'effective_to'], 'idx_validity_range');

                // Foreign keys
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_versions');
    }
};
