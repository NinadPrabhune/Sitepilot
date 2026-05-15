<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create financial_gate_blocks table for pre-transaction validation blocks
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - Schema::hasTable() prevents duplicate creation
 * - No data modifications
 * - All FK references guarded by hasTable() in separate commits
 *
 * Operation Order Rationale:
 * - Depends on users table only
 * - Used by financial transaction workflows
 * - Create early in financial module batch
 *
 * Production Risks:
 * - None - empty table creation
 *
 * Rollback Safety:
 * - Simple drop table
 *
 * Deployment Notes:
 * - Batch 1: Core Financial Tables
 * - Independent, no prerequisites beyond users
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_gate_blocks')) {
            Schema::create('financial_gate_blocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->comment('User being blocked');
                $table->string('entity_type', 20)->comment('Type of entity (invoice, payment, etc.)');
                $table->unsignedBigInteger('entity_id')->comment('Specific entity ID');
                $table->text('reason')->comment('Why the block exists');
                $table->json('requirements')->nullable()->comment('JSON array of requirements to lift block');
                $table->json('warnings')->nullable()->comment('JSON array of warnings');
                $table->timestamps();

                // Indexes for query patterns
                $table->index('user_id', 'idx_gate_user');
                $table->index('entity_type', 'idx_gate_entity');
                $table->index(['entity_type', 'entity_id'], 'idx_gate_entity_lookup');
                $table->index('created_at', 'idx_gate_created');

                // Foreign keys (safe - users table exists)
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade'); // Clean up when user removed
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_gate_blocks');
    }
};
