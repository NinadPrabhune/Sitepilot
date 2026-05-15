<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create workflow_state_histories for detailed state transition tracking
 * Risk Level: LOW - Table creation only
 *
 * CONTEXT:
 * - Specifically tracks state changes for entities
 * - More focused than workflow_audits
 * - Used for workflow visualization and SLA tracking
 *
 * SAFETY:
 * - hasTable() guard
 * - FKs to parent entities and users
 * - No data changes
 *
 * Operation Order:
 * - Independent but references various entity tables
 * - Generic foreign keys (entity_type + entity_id)
 *
 * Production Risks:
 * - Moderate volume (every state change)
 * - Index carefully for performance
 *
 * Rollback Safety:
 * - Drop table
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation
 * - Complement to workflow_audits
 * - May need composite indexes tuned to actual queries
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_state_histories')) {
            Schema::create('workflow_state_histories', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50)->comment('Table name of entity');
                $table->unsignedBigInteger('entity_id')->comment('Record ID');
                $table->string('from_state', 50)->nullable();
                $table->string('to_state', 50);
                $table->unsignedBigInteger('transitioned_by')->nullable();
                $table->timestamp('transitioned_at')->useCurrent();
                $table->text('comment')->nullable();
                $table->json('context')->nullable()->comment('Additional data at transition time');
                $table->decimal('duration_in_previous_state_hours', 8, 2)->nullable()->comment('Time spent in from_state');

                // SLA tracking
                $table->timestamp('sla_deadline')->nullable();
                $table->boolean('sla_met')->nullable();
                $table->timestamp('sla_resolved_at')->nullable();

                $table->timestamps();

                // Indexes for timeline reconstruction
                $table->index(['entity_type', 'entity_id'], 'idx_entity_timeline');
                $table->index(['entity_type', 'to_state', 'transitioned_at'], 'idx_state_timeline');
                $table->index('transitioned_by', 'idx_transitioner');
                $table->index('transitioned_at', 'idx_transition_time');
                $table->index(['to_state', 'transitioned_at'], 'idx_state_age');

                // Foreign keys
                $table->foreign('transitioned_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_state_histories');
    }
};
