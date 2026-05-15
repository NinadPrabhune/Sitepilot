<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create financial_postings table for tracking financial transactions
 * Risk Level: LOW - Simple table creation with no data modifications
 *
 * SAFETY CHECKS:
 * - Uses Schema::hasTable() guard for idempotency
 * - Creates table only if missing
 * - No data modifications
 *
 * Operation Order Rationale:
 * - Financial postings must exist before any posting-related FK references
 * - This is a core financial table - create early in migration sequence
 *
 * Production Risks:
 * - Minimal: Table creation is atomic in MySQL
 * - Foreign key checks require parent tables (users) to exist (they do)
 * - No locking concerns for empty table
 *
 * Rollback Safety:
 * - Simple dropIfExists - safe as it only removes the newly created table
 * - No data loss concern (table didn't exist before migration)
 *
 * Deployment Notes:
 * - Place after Batch 1 prerequisites (users table exists)
 * - Can be deployed independently
 * - No data backfill required at this stage
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only create if not exists (idempotent)
        if (!Schema::hasTable('financial_postings')) {
            Schema::create('financial_postings', function (Blueprint $table) {
                $table->id();
                $table->string('posting_id', 50)->unique()->comment('Unique identifier for financial posting');
                $table->string('entity_type', 20)->comment('Type of entity being posted (invoice, payment, etc.)');
                $table->unsignedBigInteger('entity_id')->comment('Reference to the entity');
                $table->decimal('amount', 15, 2)->comment('Posted amount');
                $table->unsignedBigInteger('posted_by')->comment('User who created the posting');
                $table->enum('status', ['posted', 'reversed'])->default('posted')->comment('Posting status');
                $table->timestamps();

                // Performance indexes for query patterns
                $table->index('entity_type', 'idx_posting_entity');
                $table->index('posted_by', 'idx_posting_user');
                $table->index('status', 'idx_posting_status');
                $table->index(['entity_type', 'entity_id'], 'idx_posting_entity_lookup');

                // Foreign key constraints
                $table->foreign('posted_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('restrict'); // Prevent orphaned posting records
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_postings');
    }
};
