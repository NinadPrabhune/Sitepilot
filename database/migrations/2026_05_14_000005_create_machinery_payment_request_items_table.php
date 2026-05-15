<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create machinery_payment_request_items table for detailed billing breakdown
 * Risk Level: LOW - Table creation only
 *
 * CRITICAL CONTEXT:
 * - Child table of machinery_payment_requests
 * - Stores line-item breakdown of payment requests by date range
 * - Supports billing reconciliation and audit trails
 *
 * SAFETY CHECKS:
 * - hasTable() guard for idempotency
 * - FK to machinery_payment_requests checked at runtime (table exists check)
 * - No data modifications
 *
 * Operation Order Rationale:
 * - Must be created AFTER machinery_payment_requests exists
 * - No other tables depend on this directly
 * - Can be created independently once parent table exists
 *
 * Production Risks:
 * - None - empty table creation
 *
 * Rollback Safety:
 * - Drops only the new table
 * - Parent table remains intact
 *
 * Deployment Notes:
 * - Batch 2: Machinery Module
 * - After machinery_payment_requests migration
 * - No backfill needed initially
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machinery_payment_request_items')) {
            Schema::create('machinery_payment_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_request_id')
                    ->constrained('machinery_payment_requests')
                    ->onDelete('cascade'); // Clean up if parent request deleted

                $table->foreignId('machinery_id')->constrained()->onDelete('restrict');
                $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('restrict');
                $table->foreignId('workspace_id')->constrained()->onDelete('cascade');

                // Date range for this billing item
                $table->date('from_date');
                $table->date('to_date');

                // Usage measurements
                $table->decimal('total_hours', 10, 2)->default(0);
                $table->decimal('total_diesel', 10, 2)->default(0);

                // Financial breakdown
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('rate_per_hour', 10, 2)->default(0);
                $table->decimal('diesel_rate', 10, 2)->default(0);
                $table->decimal('diesel_cost', 10, 2)->default(0);

                // Status tracking
                $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
                $table->text('remarks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Unique constraint to prevent duplicate billing periods per machinery
                $table->unique(
                    ['payment_request_id', 'machinery_id', 'from_date', 'to_date'],
                    'unique_payment_item_period'
                );

                // Performance indexes for common queries
                $table->index(['machinery_id', 'from_date', 'to_date'], 'idx_machinery_period');
                $table->index(['payment_request_id', 'status'], 'idx_request_status');
                $table->index(['supplier_id', 'workspace_id'], 'idx_supplier_ws');
                $table->index(['from_date', 'to_date'], 'idx_date_range');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_payment_request_items');
    }
};
