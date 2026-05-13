<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machinery Ledger Table Migration
 * 
 * CRITICAL ARCHITECTURAL DECISIONS:
 * 
 * 1. entry_direction (credit/debit) - Explicit direction field for cleaner queries
 *    - Makes SUM(CASE WHEN entry_direction = 'credit' THEN amount ELSE -amount END) simpler
 *    - Separates direction from entry_type for better query performance
 * 
 * 2. NO unique constraint on (reference_type, reference_id, entry_type)
 *    - Would block reversal entries needed for corrections
 *    - Idempotency enforced in service layer instead
 *    - Allows: original entry + reversal entry + correction entry for same reference
 * 
 * 3. Immutability fields (reversed_entry_id, is_reversal)
 *    - NO delete/update operations on ledger entries
 *    - Corrections use reversal pattern: mark old as reversed, create new correction
 *    - Preserves complete audit trail
 * 
 * 4. Locking fields (is_locked, locked_at, locked_by)
 *    - Ledger entries locked after payment completion
 *    - Prevents accidental modifications to paid periods
 *    - Maintains financial integrity
 * 
 * 5. Indexes optimized for:
 *    - machinery_id queries (most common)
 *    - entry_direction for SUM queries
 *    - date for chronological queries
 *    - (machinery_id, date) for balance calculations
 *    - (reference_type, reference_id) for idempotency checks in service layer
 *    - is_locked for filtering locked entries
 * 
 * 6. Foreign key constraints with cascade
 *    - machinery_id → machineries (cascade on delete)
 *    - reversed_entry_id → machinery_ledger (cascade on delete)
 *    - locked_by → users (set null on delete)
 * 
 * PERFORMANCE CONSIDERATIONS:
 * - running_balance column for fast balance queries
 * - Recalculation command available for integrity checks
 * - lockForUpdate() used in service layer to prevent race conditions
 * - Optional snapshot table for large-scale historical balance queries
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_ledger', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // Foreign key to machinery (RESTRICT delete - financial history must be preserved)
            $table->foreignId('machinery_id')
                ->constrained('machineries')
                ->onDelete('restrict');
            
            // Workspace ID for multi-workspace data isolation
            $table->foreignId('workspace_id')
                ->constrained('work_spaces')
                ->onDelete('restrict')
                ->index();
            
            // Entry direction - explicit credit/debit for cleaner queries
            $table->enum('entry_direction', ['credit', 'debit']);
            
            // Entry type - simplified without direction in name
            $table->enum('entry_type', ['reading', 'diesel', 'maintenance', 'advance', 'payment', 'transfer']);
            
            // Polymorphic reference to source entity (controlled via code enum in service)
            $table->string('reference_type', 50); // Controlled via MachineryLedgerService constants
            $table->unsignedBigInteger('reference_id');
            
            // Financial amount (always positive, direction determined by entry_direction)
            // Increased precision for large projects and long-running ledgers
            $table->decimal('amount', 15, 2);
            
            // Running balance (calculated field for performance)
            // Increased precision to match amount
            $table->decimal('running_balance', 15, 2);
            
            // Date of the transaction
            $table->date('date');
            
            // Optional description
            $table->text('description')->nullable();
            
            // Metadata for additional information (JSON)
            $table->json('metadata')->nullable();
            
            // Immutability fields - for reversal pattern
            $table->foreignId('reversed_entry_id')
                ->nullable()
                ->constrained('machinery_ledger')
                ->onDelete('restrict'); // RESTRICT - preserve audit chain, never cascade delete internally
            $table->boolean('is_reversal')->default(false);
            
            // Locking fields - lock entries after payment
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for query optimization
            $table->index('machinery_id');
            $table->index('entry_direction'); // For SUM queries
            $table->index('entry_type');
            $table->index('date');
            $table->index(['machinery_id', 'date']); // For balance calculations
            $table->index(['machinery_id', 'date', 'is_reversal']); // Composite index for core query
            $table->index(['machinery_id', 'workspace_id', 'date', 'id']); // CRITICAL: For balance lookups with ordering
            $table->index(['reference_type', 'reference_id']); // For idempotency checks
            $table->index('reversed_entry_id'); // For whereNull('reversed_entry_id') queries
            $table->index('is_locked'); // For filtering locked entries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_ledger');
    }
};
