<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION
 *
 * Purpose: Create machinery_supplier_rates table for supplier-specific billing rates
 * Risk Level: LOW - Table creation only
 *
 * SAFETY CHECKS:
 * - hasTable() ensures idempotency
 * - FKs to machinery and suppliers (core tables)
 * - No data modifications
 *
 * Operation Order Rationale:
 * - Alternative rate source to machinery.rate column
 * - Supplier-specific contract rates
 * - Create after machinery and suppliers exist
 *
 * Production Risks:
 * - None - empty reference table
 *
 * Rollback Safety:
 * - Drop table only
 *
 * Deployment Notes:
 * - Batch 2: Machinery Module
 * - Used by billing engine for rate lookups
 * - Overrides machinery.base_rate when present
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machinery_supplier_rates')) {
            Schema::create('machinery_supplier_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id');
                $table->foreignId('workspace_id')->constrained()->onDelete('cascade');

                // Rate details
                $table->decimal('rate_per_hour', 10, 2)->comment('Custom rate for this supplier');
                $table->decimal('diesel_rate', 10, 2)->nullable()->comment('Supplier-specific diesel rate');
                $table->string('rate_type', 20)->default('standard'); // standard, overtime, weekend
                $table->text('terms')->nullable();

                // Validity period
                $table->date('effective_from');
                $table->date('effective_to')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Unique constraint: one active rate per machinery+supplier+type at a time
                $table->unique(
                    ['machinery_id', 'supplier_id', 'rate_type', 'effective_from'],
                    'unique_supplier_rate_definition'
                );

                // Indexes
                $table->index(['machinery_id', 'supplier_id'], 'idx_machinery_supplier');
                $table->index(['supplier_id', 'effective_from'], 'idx_supplier_effective');
                $table->index(['workspace_id', 'effective_from'], 'idx_ws_effective');
                $table->index(['effective_from', 'effective_to'], 'idx_validity_range');

                // Foreign keys
                $table->foreign('machinery_id')
                    ->references('id')
                    ->on('machineries')
                    ->onDelete('cascade');

                $table->foreign('supplier_id')
                    ->references('id')
                    ->on('suppliers')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_supplier_rates');
    }
};
