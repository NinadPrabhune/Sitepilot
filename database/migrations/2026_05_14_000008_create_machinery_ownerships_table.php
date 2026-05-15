<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create machinery_ownerships table to track ownership/lease history
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - hasTable() guard
 * - FKs validated (machineries, suppliers exist)
 * - No data changes
 *
 * Operation Order Rationale:
 * - Depends on: machineries, suppliers
 * - Tracks ownership history independent of billing
 * - Create after core machinery tables
 *
 * Production Risks:
 * - None
 *
 * Rollback Safety:
 * - Drop table only
 *
 * Deployment Notes:
 * - Batch 2: Machinery Module
 * - Prerequisites: machineries, suppliers tables
 * - Ownership tracking for financial reconciliation
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machinery_ownerships')) {
            Schema::create('machinery_ownerships', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id')->nullable()->comment('Owner/lessor/supplier');
                $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
                $table->enum('ownership_type', ['owned', 'leased', 'rented', 'contract'])->default('owned');
                $table->date('ownership_start');
                $table->date('ownership_end')->nullable();
                $table->decimal('purchase_price', 15, 2)->nullable();
                $table->string('purchase_order_reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['machinery_id', 'ownership_start', 'ownership_end'], 'idx_machinery_ownership_period');
                $table->index(['supplier_id', 'ownership_start'], 'idx_supplier_ownership');
                $table->index('ownership_type', 'idx_ownership_type');
                $table->index(['workspace_id', 'ownership_start'], 'idx_ws_ownership');

                // Foreign keys
                $table->foreign('machinery_id')
                    ->references('id')
                    ->on('machineries')
                    ->onDelete('cascade');

                $table->foreign('supplier_id')
                    ->references('id')
                    ->on('suppliers')
                    ->onDelete('set null');

                // Prevent overlapping ownership periods for same machinery
                // $table->unique(['machinery_id', 'ownership_start']);  -- Could be unique if no overlaps allowed
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_ownerships');
    }
};
