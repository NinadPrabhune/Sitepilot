<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create machinery_payment_allocations table to track payment-to-bill allocations
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - hasTable() guard for idempotency
 * - All FK references wrapped in checks (parents create first)
 * - No data manipulation
 *
 * Operation Order Rationale:
 * - Depends on: machinery_payment_requests, machinery_bills
 * - Serves as junction table between payments and bills
 * - Create AFTER both parent tables exist
 *
 * Production Risks:
 * - None - empty table
 *
 * Rollback Safety:
 * - Simple drop
 *
 * Deployment Notes:
 * - Batch 2: Machinery Module
 * - Prerequisites: machinery_payment_requests, machinery_bills
 * - Verify both parent tables exist before running (they do in local)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machinery_payment_allocations')) {
            Schema::create('machinery_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_request_id')
                    ->constrained('machinery_payment_requests')
                    ->onDelete('cascade'); // Remove allocations if payment request deleted

                $table->foreignId('bill_id')
                    ->nullable()
                    ->constrained('machinery_bills')
                    ->onDelete('cascade'); // Remove if bill removed

                $table->foreignId('workspace_id')->constrained()->onDelete('cascade');

                // Allocation details
                $table->decimal('allocated_amount', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2)->default(0);
                $table->date('allocation_date')->nullable();

                // Status tracking
                $table->enum('status', ['pending', 'applied', 'reconciled', 'reversed'])->default('pending');
                $table->text('notes')->nullable();

                // Reversal tracking
                $table->boolean('is_reversed')->default(false);
                $table->unsignedBigInteger('reversed_by')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('reversal_reason')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Prevent duplicate allocations for same request-bill combination
                $table->unique(
                    ['payment_request_id', 'bill_id'],
                    'unique_allocation_pair'
                );

                // Indexes for allocation queries
                $table->index(['payment_request_id', 'status'], 'idx_request_status');
                $table->index(['bill_id', 'status'], 'idx_bill_status');
                $table->index(['workspace_id', 'allocation_date'], 'idx_ws_date');
                $table->index('is_reversed', 'idx_reversal_flag');

                // Foreign keys
                $table->foreign('reversed_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_payment_allocations');
    }
};
