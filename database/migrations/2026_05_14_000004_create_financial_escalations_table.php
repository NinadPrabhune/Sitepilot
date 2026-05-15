<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create financial_escalations table for approval escalation workflow
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - Schema::hasTable() guard
 * - No data modifications
 * - FKs to users table only (well-established)
 *
 * Operation Order Rationale:
 * - Part of financial approval workflow
 * - Create before any escalation logic is invoked
 * - Independent of posting/billing tables
 *
 * Production Risks:
 * - None - empty table, no queries affected
 *
 * Rollback Safety:
 * - Simple drop
 *
 * Deployment Notes:
 * - Batch 1: Core Financial Tables
 * - Standalone, no child dependencies
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_escalations')) {
            Schema::create('financial_escalations', function (Blueprint $table) {
                $table->id();
                $table->string('escalation_id', 50)->unique()->comment('Human-readable escalation reference');
                $table->unsignedBigInteger('user_id')->comment('User who escalated');
                $table->string('entity_type', 20)->comment('Type of entity being escalated');
                $table->unsignedBigInteger('entity_id')->comment('Entity ID');
                $table->string('escalation_type', 30)->comment('Category of escalation');
                $table->text('reason')->comment('Why escalation was needed');
                $table->json('requirements')->nullable()->comment('Conditions to resolve');
                $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval');
                $table->unsignedBigInteger('approver_id')->nullable()->comment('Approver user');
                $table->text('approver_comments')->nullable();
                $table->json('data')->nullable()->comment('Additional context');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                // Indexes for fast lookups
                $table->index('user_id', 'idx_escalation_user');
                $table->index('escalation_type', 'idx_escalation_type');
                $table->index('status', 'idx_escalation_status');
                $table->index(['status', 'created_at'], 'idx_status_created');
                $table->index(['entity_type', 'entity_id'], 'idx_entity_lookup');

                // Foreign keys
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->foreign('approver_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_escalations');
    }
};
