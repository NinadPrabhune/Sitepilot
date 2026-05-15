<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create workflow_audits table for state change audit
 * Risk Level: LOW - Table creation only
 *
 * CONTEXT:
 * - Generic workflow audit log
 * - Records who changed what and when across all entities
 * - Supports compliance and forensics
 *
 * SAFETY:
 * - hasTable() guard
 * - FKs to users only
 * - No data modifications
 *
 * Operation Order:
 * - Independent, no dependencies beyond users
 * - Used by workflow engine
 *
 * Production Risks:
 * - High write volume expected (every state change)
 * - Consider partitioning by month/year for large deployments
 * - Add TTL policy if needed
 *
 * Rollback Safety:
 * - Drop table
 *
 * Deployment Notes:
 * - Batch 4: Audit/Reconciliation
 * - Core audit table
 * - Monitor table size - implement archival strategy
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_audits')) {
            Schema::create('workflow_audits', function (Blueprint $table) {
                $table->id();
                $table->string('audit_id', 50)->unique()->comment('Reference for audit trail');
                $table->string('entity_type', 50)->comment('Model class or table name');
                $table->unsignedBigInteger('entity_id')->comment('Record ID');
                $table->unsignedBigInteger('user_id')->nullable()->comment('Who performed action');
                $table->string('user_type', 50)->nullable()->comment('User model class');
                $table->string('action', 50)->comment('Action performed: create, update, state_change, delete');
                $table->enum('change_scope', ['attribute', 'state', 'bulk'])->default('attribute');

                // Before/after values
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('changed_attributes')->nullable()->comment('Array of changed field names');

                // Context
                $table->string('source_app', 30)->default('web')->comment('web, api, cli, import');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('metadata')->nullable();

                // Workflow specific
                $table->string('from_state', 50)->nullable()->comment('Previous state');
                $table->string('to_state', 50)->nullable()->comment('New state');
                $table->text('transition_comment')->nullable();

                // Performance
                $table->decimal('execution_time_ms', 8, 2)->nullable()->comment('Time taken for operation');

                $table->timestamps();

                // Indexes
                $table->index(['entity_type', 'entity_id'], 'idx_entity_lookup');
                $table->index(['user_id', 'created_at'], 'idx_user_timeline');
                $table->index(['action', 'created_at'], 'idx_action_timeline');
                $table->index(['entity_type', 'action'], 'idx_entity_action');
                $table->index(['from_state', 'to_state'], 'idx_state_transition');
                $table->index('created_at', 'idx_created_at');

                // Foreign keys
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_audits');
    }
};
