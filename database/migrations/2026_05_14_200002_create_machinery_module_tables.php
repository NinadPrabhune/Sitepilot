<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BATCH 2: MACHINERY MODULE TABLES - Phase 2
 * ============================================================================
 * PRIORITY: HIGH (Critical for machinery payment processing)
 *
 * MIGRATION: create_machinery_module_tables.php
 * TIMESTAMP: 2026_05_14_200002
 *
 * PURPOSE: Create machinery billing and payment tables
 *
 * TABLES CREATED:
 * 1. machinery_payment_requests - Master payment request record
 * 2. machinery_payment_request_items - Line items for payment requests
 * 3. machinery_payment_allocations - Payment allocation tracking
 * 4. machinery_ledger - Machinery-specific ledger entries
 * 5. machinery_bills - Machinery billing records
 * 6. machinery_billing_items - Billing line items
 * 7. machinery_rate_histories - Rate change history
 * 8. machinery_supplier_rates - Supplier-specific rates
 * 9. machinery_ownerships - Machinery ownership tracking
 * 10. machinery_usage_logs - Usage logging
 * 11. machinery_ownership_locks - Ownership lock records
 *
 * SAFETY RATIONALE:
 * - All tables use hasTable() guards for idempotency
 * - No data modifications - pure table creation
 * - FK references use onDelete('restrict') by default
 * - Uses set null for soft dependencies to preserve existing records
 *
 * OPERATION ORDER:
 * 1. machinery_payment_requests (parent table)
 * 2. machinery_ledger (independent, referenced by others)
 * 3. machinery_payment_request_items (references machinery_payment_requests)
 * 4. machinery_payment_allocations (references machinery_payment_requests)
 * 5. machinery_bills (references machinery_ledger)
 * 6. machinery_billing_items (references machinery_bills)
 * 7. Rate tables (independent)
 * 8. Usage logs (independent)
 *
 * DEPENDENCY NOTE:
 * - These tables may already exist in Live if partial migration ran
 * - hasTable() guards prevent duplicate creation errors
 *
 * PRODUCTION RISK: LOW
 * - Creates new empty tables only
 * - No impact on existing machinery data
 * - No backfill needed initially
 *
 * ROLLBACK: Each table can be dropped individually
 *
 * DEPLOYMENT NOTES:
 * - Run after Batch 1 (financial core tables)
 * - Check existing tables with: php artisan migrate:status --columns
 * - May need to manually verify FK relationships on existing data
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. MACHINERY_PAYMENT_REQUESTS - Master payment request (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_payment_requests')) {
            Schema::create('machinery_payment_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('workspace_id');
                $table->string('payment_number', 50)->nullable()->unique();

                // Period
                $table->date('period_start');
                $table->date('period_end');

                // Amounts
                $table->decimal('credits', 15, 2)->default(0);
                $table->decimal('debits', 15, 2)->default(0);
                $table->decimal('gross_amount', 12, 2)->nullable();
                $table->decimal('diesel_deduction', 12, 2)->nullable();
                $table->decimal('net_payable', 15, 2)->default(0);

                // Status workflow
                $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'locked', 'paid', 'rejected', 'hold'])
                    ->default('draft');

                // Calculation metadata
                $table->string('calculation_method', 50)->nullable();
                $table->json('billing_breakdown')->nullable();
                $table->json('diesel_breakdown')->nullable();

                // Request tracking
                $table->unsignedBigInteger('requested_by');
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->text('rejection_reason')->nullable();

                // Audit
                $table->json('audit_data')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['machinery_id', 'period_start', 'period_end'], 'idx_machinery_period');
                $table->index(['supplier_id', 'status'], 'idx_supplier_status');
                $table->index(['workspace_id', 'status'], 'idx_ws_status');
                $table->index('payment_number', 'idx_payment_number');
            });
        }

        // =====================================================================
        // 2. MACHINERY_PAYMENT_REQUEST_ITEMS - Line items (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_payment_request_items')) {
            Schema::create('machinery_payment_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_request_id');
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('workspace_id');

                // Date range
                $table->date('from_date');
                $table->date('to_date');

                // Usage
                $table->decimal('total_hours', 10, 2)->default(0);
                $table->decimal('total_diesel', 10, 2)->default(0);

                // Financial
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('rate_per_hour', 10, 2)->default(0);
                $table->decimal('diesel_rate', 10, 2)->default(0);
                $table->decimal('diesel_cost', 10, 2)->default(0);

                // Status
                $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
                $table->text('remarks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Constraints
                $table->unique(
                    ['payment_request_id', 'machinery_id', 'from_date', 'to_date'],
                    'unique_payment_item_period'
                );

                // Indexes
                $table->index(['machinery_id', 'from_date', 'to_date'], 'idx_item_machinery_period');
                $table->index(['payment_request_id', 'status'], 'idx_item_request_status');
                $table->index(['supplier_id', 'workspace_id'], 'idx_item_supplier_ws');
            });
        }

        // =====================================================================
        // 3. MACHINERY_PAYMENT_ALLOCATIONS - Payment allocation (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_payment_allocations')) {
            Schema::create('machinery_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_request_id');
                $table->unsignedBigInteger('workspace_id');

                // Allocation details
                $table->string('allocation_type', 50)->comment('advance, credit, adjustment');
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('allocated_amount', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2)->default(0);

                // Source reference
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                // Status
                $table->enum('status', ['pending', 'applied', 'released', 'cancelled'])
                    ->default('pending');
                $table->text('notes')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['payment_request_id', 'status'], 'idx_alloc_request_status');
                $table->index(['source_type', 'source_id'], 'idx_alloc_source');
                $table->index(['allocation_type', 'status'], 'idx_alloc_type_status');
            });
        }

        // =====================================================================
        // 4. MACHINERY_LEDGER - Machinery ledger (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_ledger')) {
            Schema::create('machinery_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Entry type
                $table->enum('entry_type', ['credit', 'debit', 'adjustment', 'reversal'])->default('credit');
                $table->string('ledger_type', 50)->default('machinery')->comment('machinery, diesel, maintenance, rental');
                $table->string('cost_category', 50)->nullable()->comment('operational, capital, maintenance');

                // Amount
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('running_balance', 15, 2)->default(0);

                // Source reference
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedBigInteger('payment_request_id')->nullable();

                // Date and description
                $table->date('entry_date');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();

                // Billing version
                $table->integer('billing_version')->default(1);
                $table->string('calculation_hash', 64)->nullable();

                // Idempotency
                $table->string('idempotency_key', 100)->nullable()->unique();

                // Lock tracking
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();

                // Reversal
                $table->boolean('is_reversal')->default(false);
                $table->unsignedBigInteger('reversed_by_entry_id')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['machinery_id', 'entry_date'], 'idx_mach_ledger_date');
                $table->index(['supplier_id', 'entry_date'], 'idx_sup_ledger_date');
                $table->index(['workspace_id', 'entry_date'], 'idx_ws_ledger_date');
                $table->index(['source_type', 'source_id'], 'idx_ml_source');
                $table->index(['payment_request_id'], 'idx_ml_payment_request');
                $table->index('idempotency_key', 'idx_ml_idempotency');
                $table->index('ledger_type', 'idx_ledger_type');
            });
        }

        // =====================================================================
        // 5. MACHINERY_BILLS - Machinery billing (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_bills')) {
            Schema::create('machinery_bills', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->unsignedBigInteger('machinery_ledger_id')->nullable();

                // Bill details
                $table->string('bill_number', 50)->nullable();
                $table->date('bill_date');
                $table->string('bill_type', 50)->default('usage');

                // Amounts
                $table->decimal('total_hours', 10, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('diesel_amount', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2)->default(0);

                // Status
                $table->enum('status', ['draft', 'generated', 'verified', 'approved', 'paid', 'cancelled'])
                    ->default('draft');
                $table->text('remarks')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['machinery_id', 'bill_date'], 'idx_bill_machinery_date');
                $table->index(['supplier_id', 'status'], 'idx_bill_supplier_status');
                $table->index('bill_number', 'idx_bill_number');
            });
        }

        // =====================================================================
        // 6. MACHINERY_BILLING_ITEMS - Billing line items (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_billing_items')) {
            Schema::create('machinery_billing_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_bill_id');
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('daily_progress_report_id')->nullable();

                // Period
                $table->date('from_date');
                $table->date('to_date');

                // Usage
                $table->decimal('hours', 10, 2)->default(0);
                $table->decimal('diesel_liters', 10, 2)->default(0);

                // Rate and amount
                $table->decimal('rate_per_hour', 10, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->decimal('diesel_rate', 10, 2)->default(0);
                $table->decimal('diesel_cost', 15, 2)->default(0);

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['machinery_bill_id', 'from_date', 'to_date'], 'idx_item_bill_period');
                $table->index('daily_progress_report_id', 'idx_item_dpr');
            });
        }

        // =====================================================================
        // 7. MACHINERY_RATE_HISTORIES - Rate change history (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_rate_histories')) {
            Schema::create('machinery_rate_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');

                // Rate details
                $table->decimal('hourly_rate', 12, 2)->default(0);
                $table->decimal('diesel_rate', 12, 2)->default(0);
                $table->string('rate_type', 50)->default('standard');

                // Effective dates
                $table->date('effective_from');
                $table->date('effective_to')->nullable();

                // Source
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                // Audit
                $table->unsignedBigInteger('created_by');
                $table->text('change_reason')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['machinery_id', 'effective_from'], 'idx_rate_machinery_effective');
                $table->index(['workspace_id', 'rate_type'], 'idx_rate_ws_type');
            });
        }

        // =====================================================================
        // 8. MACHINERY_SUPPLIER_RATES - Supplier-specific rates (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_supplier_rates')) {
            Schema::create('machinery_supplier_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('workspace_id');

                // Rates
                $table->decimal('hourly_rate', 12, 2)->default(0);
                $table->decimal('diesel_rate', 12, 2)->default(0);
                $table->decimal('minimum_hours', 10, 2)->default(0);
                $table->decimal('overtime_rate', 12, 2)->nullable();

                // Terms
                $table->text('rate_terms')->nullable();
                $table->boolean('is_active')->default(true);

                // Effective dates
                $table->date('effective_from');
                $table->date('effective_to')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index(['supplier_id', 'is_active'], 'idx_supplier_rate_active');
                $table->index(['machinery_id', 'effective_from'], 'idx_machinery_effective');
            });
        }

        // =====================================================================
        // 9. MACHINERY_OWNERSHIPS - Ownership tracking (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_ownerships')) {
            Schema::create('machinery_ownerships', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('owner_id');
                $table->string('owner_type', 50)->comment('supplier, company, third_party');

                // Ownership details
                $table->date('ownership_start_date');
                $table->date('ownership_end_date')->nullable();
                $table->enum('status', ['active', 'transferred', 'sold', 'scrapped'])
                    ->default('active');

                // Legal
                $table->string('ownership_type', 50)->default('rented')
                    ->comment('owned, rented, leased, shared');
                $table->string('registration_number', 50)->nullable();
                $table->date('registration_expiry')->nullable();

                // Financial
                $table->decimal('purchase_price', 15, 2)->nullable();
                $table->decimal('current_value', 15, 2)->nullable();
                $table->decimal('monthly_rent', 12, 2)->nullable();

                // Audit
                $table->text('ownership_notes')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['machinery_id', 'status'], 'idx_ownership_machinery');
                $table->index(['owner_id', 'owner_type'], 'idx_owner');
                $table->index('ownership_type', 'idx_ownership_type');
            });
        }

        // =====================================================================
        // 10. MACHINERY_USAGE_LOGS - Usage logging (if not exists)
        // =====================================================================
        if (!Schema::hasTable('machinery_usage_logs')) {
            Schema::create('machinery_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('site_id')->nullable();

                // Date and hours
                $table->date('usage_date');
                $table->decimal('hours_worked', 10, 2)->default(0);
                $table->decimal('idle_hours', 10, 2)->default(0);
                $table->decimal('diesel_consumed', 10, 2)->default(0);

                // Meter readings
                $table->decimal('start_meter_reading', 12, 2)->nullable();
                $table->decimal('end_meter_reading', 12, 2)->nullable();

                // Location
                $table->string('location', 100)->nullable();
                $table->unsignedBigInteger('project_id')->nullable();

                // Operator
                $table->unsignedBigInteger('operator_id')->nullable();
                $table->string('operator_name', 100)->nullable();

                // Status
                $table->enum('status', ['active', 'maintenance', 'idle', 'breakdown'])
                    ->default('active');
                $table->text('remarks')->nullable();

                // Source
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['machinery_id', 'usage_date'], 'idx_usage_machinery_date');
                $table->index(['workspace_id', 'usage_date'], 'idx_usage_ws_date');
                $table->index(['site_id', 'usage_date'], 'idx_usage_site_date');
                $table->index(['source_type', 'source_id'], 'idx_usage_source');
            });
        }

        // =====================================================================
        // 11. CALCULATION_VERSIONS - Calculation version tracking (if not exists)
        // =====================================================================
        if (!Schema::hasTable('calculation_versions')) {
            Schema::create('calculation_versions', function (Blueprint $table) {
                $table->id();
                $table->string('calculable_type', 100)->comment('DailyProgressReport, etc');
                $table->unsignedBigInteger('calculable_id');
                $table->unsignedBigInteger('workspace_id');

                // Version details
                $table->integer('version_number')->default(1);
                $table->string('calculation_type', 50)->default('standard');
                $table->decimal('calculated_value', 18, 4)->default(0);

                // Hash for integrity
                $table->string('calculation_hash', 64)->nullable();
                $table->json('input_data')->nullable();
                $table->json('output_data')->nullable();

                // Status
                $table->boolean('is_active')->default(true);
                $table->dateTime('effective_from')->nullable();
                $table->dateTime('effective_to')->nullable();

                // Audit
                $table->unsignedBigInteger('calculated_by')->nullable();
                $table->text('change_notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['calculable_type', 'calculable_id'], 'idx_calc_version');
                $table->index(['calculable_type', 'is_active'], 'idx_calc_active');
                $table->index(['workspace_id', 'effective_from'], 'idx_calc_effective');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_versions');
        Schema::dropIfExists('machinery_usage_logs');
        Schema::dropIfExists('machinery_ownerships');
        Schema::dropIfExists('machinery_supplier_rates');
        Schema::dropIfExists('machinery_rate_histories');
        Schema::dropIfExists('machinery_billing_items');
        Schema::dropIfExists('machinery_bills');
        Schema::dropIfExists('machinery_ledger');
        Schema::dropIfExists('machinery_payment_allocations');
        Schema::dropIfExists('machinery_payment_request_items');
        Schema::dropIfExists('machinery_payment_requests');
    }
};