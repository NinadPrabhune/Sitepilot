<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CRITICAL: This creates the payment traceability map
     * Enables reconstruction of original financial path for audit purposes
     * Stores the transformation mapping from PO → Invoice
     */
    public function up(): void
    {
        Schema::create('payment_migration_map', function (Blueprint $table) {
            $table->id();
            
            // Payment identification
            $table->integer('payment_id')->unique(); // The payment that was transformed
            $table->string('payment_number')->nullable();
            
            // Original state (before migration)
            $table->integer('old_po_id')->nullable();
            $table->string('old_payment_type')->nullable();
            $table->integer('old_invoice_id')->nullable();
            $table->integer('old_allocation_id')->nullable();
            $table->decimal('old_allocated_amount', 15, 2)->nullable();
            
            // New state (after migration)
            $table->integer('new_invoice_id')->nullable();
            $table->string('new_payment_type')->nullable();
            
            // Transformation metadata
            $table->string('migration_phase')->default('phase3'); // e.g., 'phase3', 'phase3_step1'
            $table->string('migration_batch')->nullable(); // For tracking batch migrations
            $table->timestamp('migrated_at')->useCurrent();
            $table->integer('migrated_by')->nullable();
            
            // Transformation type
            $table->enum('transformation_type', [
                'direct_invoice_link',      // Already had invoice_id
                'allocation_to_invoice',    // Converted from allocation
                'manual_intervention',       // Required manual review
                'no_change',                // No transformation needed
                'error'                     // Migration error
            ])->default('no_change');
            
            // Validation
            $table->boolean('validated')->default(false);
            $table->text('validation_notes')->nullable();
            
            // Reconciliation
            $table->decimal('amount_before', 15, 2)->nullable();
            $table->decimal('amount_after', 15, 2)->nullable();
            $table->decimal('amount_difference', 15, 2)->nullable();
            
            // Audit trail
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes for audit queries
            $table->index('old_po_id');
            $table->index('new_invoice_id');
            $table->index('migration_phase');
            $table->index('migration_batch');
            $table->index('transformation_type');
        });

        Log::channel('payment_audit')->info('Critical: Created payment_migration_map table (PAYMENT TRACEABILITY)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_migration_map');
    }
};
