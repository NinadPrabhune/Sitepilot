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
     * CRITICAL: This creates the global migration guard system
     * Acts as a financial system circuit breaker to prevent:
     * - Duplicate migration execution
     * - Partial system corruption
     * - Inconsistent ledger states
     */
    public function up(): void
    {
        Schema::create('system_migration_state', function (Blueprint $table) {
            $table->id();
            
            // Migration identification
            $table->string('migration_phase')->unique(); // e.g., 'phase3', 'phase3_step1'
            $table->string('migration_name')->nullable();
            
            // State tracking
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'rolled_back'])
                  ->default('pending');
            
            // Execution metadata
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('executed_by')->nullable();
            $table->text('execution_notes')->nullable();
            
            // Safety controls
            $table->boolean('locked')->default(false); // Prevents re-execution
            $table->string('checksum')->nullable(); // Verifies data integrity
            $table->json('pre_migration_snapshot')->nullable(); // JSON snapshot of critical data
            
            // Approval gates
            $table->boolean('staging_approved')->default(false);
            $table->timestamp('staging_approved_at')->nullable();
            $table->integer('staging_approved_by')->nullable();
            
            $table->boolean('production_approved')->default(false);
            $table->timestamp('production_approved_at')->nullable();
            $table->integer('production_approved_by')->nullable();
            
            // Validation gates
            $table->boolean('validation_passed')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_results')->nullable();
            
            // Error tracking
            $table->text('error_message')->nullable();
            $table->integer('error_count')->default(0);
            
            $table->timestamps();
        });

        // Initialize Phase 3 states
        DB::table('system_migration_state')->insert([
            [
                'migration_phase' => 'phase3_data_migration',
                'migration_name' => 'PO-based to Invoice-based Payment Migration',
                'status' => 'pending',
                'locked' => false,
            ],
            [
                'migration_phase' => 'phase3_ledger_recalculation',
                'migration_name' => 'Supplier Ledger Balance Recalculation',
                'status' => 'pending',
                'locked' => false,
            ],
            [
                'migration_phase' => 'phase3_allocations_cleanup',
                'migration_name' => 'Payment Module Allocations Table Cleanup',
                'status' => 'pending',
                'locked' => false,
            ],
        ]);

        Log::channel('payment_audit')->info('Critical: Created system_migration_state table (GLOBAL MIGRATION GUARD)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_migration_state');
    }
};
