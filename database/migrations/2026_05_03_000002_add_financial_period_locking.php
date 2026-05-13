<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create financial_period_locks table
        Schema::create('financial_period_locks', function (Blueprint $table) {
            $table->id();
            $table->string('period_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(true);
            $table->integer('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('lock_reason')->nullable();
            $table->integer('unlocked_by')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['start_date', 'end_date']);
            $table->index('is_locked');
            $table->unique(['period_name', 'start_date', 'end_date']);
        });
        
        // Create journal_adjustments table for locked period corrections
        Schema::create('journal_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type'); // DailyProgressReport, DailyConsumptionMaster, etc.
            $table->integer('reference_id');
            $table->json('adjustment_data'); // Details of the adjustment
            $table->string('adjustment_reason');
            $table->integer('created_by');
            $table->integer('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
            $table->index('created_by');
        });
        
        // Add monitoring table for ledger integrity checks
        Schema::create('ledger_integrity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('check_type'); // active_entry_count, chain_integrity, etc.
            $table->json('check_results');
            $table->string('status'); // passed, failed, warning
            $table->text('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('check_type');
            $table->index('status');
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_adjustments');
        Schema::dropIfExists('financial_period_locks');
        Schema::dropIfExists('ledger_integrity_logs');
    }
};
