<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create workflow_transitions table for workflow state machine rules
 * Risk Level: LOW - Table creation only
 *
 * CONTEXT:
 * - Stores allowed state transitions and conditions
 * - Configurable workflow definition (not just code)
 * - Supports dynamic workflow changes without code deploys
 *
 * SAFETY:
 * - hasTable() guard
 * - No FKs beyond users (defines rules, doesn't reference entities)
 * - Seed data may be added later - not in this migration
 *
 * Operation Order:
 * - Independent of all entity tables
 * - Can be created at any time
 *
 * Production Risks:
 * - Minimal - small reference table
 * - Workflow engine reads it, doesn't affect existing state
 *
 * Rollback Safety:
 * - Drop table
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation (Workflow configuration)
 * - Seed with default transitions after migration
 * - Used by workflow engine to validate transitions
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_transitions')) {
            Schema::create('workflow_transitions', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50)->comment('What type of entity this applies to');
                $table->string('from_state', 50)->comment('Current state');
                $table->string('to_state', 50)->comment('Target state');
                $table->string('trigger', 50)->nullable()->comment('user, system, schedule');
                $table->json('conditions')->nullable()->comment('JSON conditions required to allow transition');
                $table->json('actions')->nullable()->comment('Side effects to execute (notifications, etc)');
                $table->boolean('is_default')->default(false)->comment('Auto-apply if conditions met');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->text('description')->nullable();

                $table->timestamps();

                // Unique constraint: one rule per from->to pair per entity
                $table->unique(['entity_type', 'from_state', 'to_state'], 'uniq_transition_rule');

                // Indexes
                $table->index(['entity_type', 'is_active'], 'idx_entity_active');
                $table->index(['from_state', 'is_active'], 'idx_from_state_active');
                $table->index(['to_state', 'is_active'], 'idx_to_state_active');

                // Foreign keys
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
