<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * FIX MIGRATION: Add Missing DPR Columns
 * ============================================================================
 * PRIORITY: HIGH
 *
 * MIGRATION: fix_missing_dpr_columns.php
 * TIMESTAMP: 2026_05_14_200007
 *
 * PURPOSE: Add missing columns to daily_progress_reports that were expected
 *          from pending Local migrations but missing in Live database.
 *
 * COLUMNS ADDED:
 * 1. machine_idle_reading - Machine idle time reading
 * 2. billable_hours - Hours eligible for billing
 * 3. calculated_amount - Calculated monetary amount
 * 4. payment_status - Payment state (unpaid/partial/paid)
 * 5. is_billed - Boolean flag for billing status
 * 6. payment_request_id - Link to machinery_payment_requests
 *
 * SAFETY RATIONALE:
 * - All columns use hasColumn() checks for idempotency
 * - All columns are nullable with safe defaults
 * - No data modification
 * - Try-catch wrapped for each column
 *
 * OPERATION ORDER:
 * - Must run after 2026_05_14_200003_create_dpr_workflow_tables.php
 * - Must run before dependent application logic
 *
 * PRODUCTION RISK: LOW
 * - Additive columns only
 * - All nullable - won't break existing rows
 *
 * DEPLOYMENT NOTES:
 * - Run this fix before other pending migrations that depend on these columns
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_progress_reports')) {
            \Log::warning('daily_progress_reports table missing - cannot add columns');
            return;
        }

        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // 1. machine_idle_reading - Machine idle time
            if (!Schema::hasColumn('daily_progress_reports', 'machine_idle_reading')) {
                try {
                    $table->decimal('machine_idle_reading', 10, 2)->nullable()->after('machine_end_reading')
                        ->comment('Idle time reading from machine meter');
                } catch (\Exception $e) {
                    \Log::warning('Could not add machine_idle_reading: ' . $e->getMessage());
                }
            }

            // 2. billable_hours - Hours eligible for billing
            if (!Schema::hasColumn('daily_progress_reports', 'billable_hours')) {
                try {
                    $table->decimal('billable_hours', 10, 2)->nullable()->after('number_of_operators')
                        ->comment('Hours eligible for billing');
                } catch (\Exception $e) {
                    \Log::warning('Could not add billable_hours: ' . $e->getMessage());
                }
            }

            // 3. calculated_amount - Calculated monetary amount
            if (!Schema::hasColumn('daily_progress_reports', 'calculated_amount')) {
                try {
                    $table->decimal('calculated_amount', 15, 2)->nullable()->after('billable_hours')
                        ->comment('Calculated monetary amount');
                } catch (\Exception $e) {
                    \Log::warning('Could not add calculated_amount: ' . $e->getMessage());
                }
            }

            // 4. payment_status - Payment state
            if (!Schema::hasColumn('daily_progress_reports', 'payment_status')) {
                try {
                    $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')
                        ->nullable()->after('calculated_amount')
                        ->comment('Payment state');
                } catch (\Exception $e) {
                    \Log::warning('Could not add payment_status: ' . $e->getMessage());
                }
            }

            // 5. is_billed - Boolean flag
            if (!Schema::hasColumn('daily_progress_reports', 'is_billed')) {
                try {
                    $table->boolean('is_billed')->default(false)->after('payment_status')
                        ->comment('Whether included in billing');
                } catch (\Exception $e) {
                    \Log::warning('Could not add is_billed: ' . $e->getMessage());
                }
            }

            // 6. payment_request_id - FK to machinery_payment_requests
            if (!Schema::hasColumn('daily_progress_reports', 'payment_request_id')) {
                try {
                    // Add unsignedBigInteger first, then try to add FK
                    $table->unsignedBigInteger('payment_request_id')->nullable()->after('is_billed')
                        ->comment('Payment request generated from this DPR');
                } catch (\Exception $e) {
                    \Log::warning('Could not add payment_request_id column: ' . $e->getMessage());
                }
            }
        });

        // Try to add foreign key for payment_request_id separately
        try {
            if (Schema::hasColumn('daily_progress_reports', 'payment_request_id') &&
                Schema::hasTable('machinery_payment_requests')) {
                Schema::table('daily_progress_reports', function (Blueprint $table) {
                    if (!$this->hasForeignKey('daily_progress_reports', 'payment_request_id')) {
                        $table->foreign('payment_request_id')
                            ->references('id')
                            ->on('machinery_payment_requests')
                            ->onDelete('set null');
                    }
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Could not add payment_request_id FK: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $columns = [
                'payment_request_id',
                'is_billed',
                'payment_status',
                'calculated_amount',
                'billable_hours',
                'machine_idle_reading',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('daily_progress_reports', $column)) {
                    try {
                        $table->dropColumn($column);
                    } catch (\Exception $e) {
                        \Log::warning('Could not drop column ' . $column . ': ' . $e->getMessage());
                    }
                }
            }
        });
    }

    /**
     * Check if foreign key exists
     */
    private function hasForeignKey(string $table, string $column): bool
    {
        try {
            $connection = Schema::getConnection();
            $sm = $connection->getDoctrineSchemaManager();
            $foreignKeys = $sm->listTableForeignKeys($connection->getDatabasePrefix() . $table);
            foreach ($foreignKeys as $fk) {
                if (in_array($column, $fk->getColumns())) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};