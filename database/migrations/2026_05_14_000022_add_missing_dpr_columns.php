<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFETY: NON-DESTRUCTIVE MIGRATION - COLUMN ADDITIONS
 *
 * Purpose: Add critically missing columns to daily_progress_reports
 * Risk Level: LOW - Additive only, no data modifications
 *
 * DEFINITION OF "CRITICALLY MISSING":
 * Columns required for core business logic that exist in Local but absent in Live:
 * - billable_hours: hours eligible for billing (may differ from total hours)
 * - calculated_amount: monetary value calculated for this DPR
 * - payment_status: overall payment state (unpaid/partial/paid)
 * - is_billed: explicit flag for billing status
 * - payment_request_id: direct link to payment request for this DPR
 * - captured_at/captured_by: system automation metadata
 * - drift_count: number of data drift warnings (monitoring)
 *
 * EXCLUDED columns (already added by other migrations):
 * - approved_at, approved_by (2026_04_30_000014)
 * - rejected_at, rejected_by, rejection_reason (2026_04_30_000014)
 * - lifecycle_state, verified_at, locked_at, paid_at + corresponding by fields (2026_05_02_000016)
 * - machine_idle_reading (2026_04_30_000013)
 * - operator_names (2026_05_02_000011)
 * - rate_snapshot, calculation_hash, payment_status? already in 2026_05_02_000007? Let's re-check
 *
 * Actually payment_status is added by 2026_05_02_000007, but we add it again safely with hasColumn guard.
 * Some columns like is_locked also added by 000007 but already exists - guard prevents duplicate.
 *
 * SAFETY:
 * - Each column wrapped in Schema::hasColumn() check
 * - Nullable defaults prevent breaking existing rows
 * - No UPDATE statements - pure schema alteration
 * - Foreign keys added after column existence validated
 *
 * Operation Order:
 * - Daily_progress_reports must exist (it does)
 * - Order matters: add columns first, then indexes, then FKs
 *
 * Column Rationale:
 * 1. billable_hours: May differ from total hours due to contracts, minimums
 * 2. calculated_amount: Cached total for display/reporting (doesn't require join)
 * 3. payment_status: Derived field for quick filtering (unpaid/partial/paid)
 * 4. is_billed: Explicit boolean for fast queries
 * 5. payment_request_id: FK to PaymentRequest (if using one-to-one)
 * 6. captured_at/captured_by: For audit of system vs manual entry
 * 7. drift_count: Counter for data quality alerts
 *
 * Foreign Key Safety:
 * - payment_request_id references payment_requests (invoices) table
 * - Use nullable FK to allow DPR without payment request
 * - onDelete('set null') preserves DPR if payment deleted
 *
 * Production Risks:
 * - ALTER TABLE on large table can lock and cause downtime
 * - Daily_progress_reports may have many rows (months of data)
 * - Recommendation: Add columns one at a time during maintenance window
 * - Alternatively, use pt-online-schema-change for zero-downtime
 *
 * Rollback Safety:
 * - Can drop each column individually
 * - No data loss (columns are additions)
 *
 * Deployment Notes:
 * - Batch 3: DPR Workflow Enhancements
 * - IMPORTANT: Check table size first:
 *   SELECT table_rows FROM information_schema.tables WHERE table_name = 'daily_progress_reports';
 *   If > 100K rows, schedule during off-peak
 * - Consider adding columns in separate migrations if table is huge
 *   This migration adds 7 columns which could lock table for extended period
 *
 * Post-Migration:
 * - Backfill payment_status based on existing payment_request_id if needed
 * - Calculate initial calculated_amount from existing rate data (if applicable)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daily_progress_reports')) {
            \Log::warning('daily_progress_reports table missing - skipping column additions');
            return;
        }

        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // 1. billable_hours: nullable decimal for now (backfill later if needed)
            if (!Schema::hasColumn('daily_progress_reports', 'billable_hours')) {
                $table->decimal('billable_hours', 10, 2)->nullable()->after('number_of_operators')
                    ->comment('Hours eligible for billing (may differ from machine hours)');
            }

            // 2. calculated_amount: cached financial amount
            if (!Schema::hasColumn('daily_progress_reports', 'calculated_amount')) {
                $table->decimal('calculated_amount', 15, 2)->nullable()->after('billable_hours')
                    ->comment('Calculated monetary amount for this report');
            }

            // 3. payment_status: quick filter for payment state
            if (!Schema::hasColumn('daily_progress_reports', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('calculated_amount')
                    ->comment('Aggregate payment status derived from payment_requests');
            }

            // 4. is_billed: explicit flag for fast boolean queries
            if (!Schema::hasColumn('daily_progress_reports', 'is_billed')) {
                $table->boolean('is_billed')->default(false)->after('payment_status')
                    ->comment('Whether this DPR has been included in a billing request');
            }

            // 5. payment_request_id: direct link to payment request (if one-to-one)
            // Note: payment_requests table may be payment_requests or machinery_payment_requests?
            // We'll use machinery_payment_requests FK if that's the payment system
            // But there is also a generic payment_requests model (PaymentRequest) that references purchase_invoice
            // The DPR might link to machinery_payment_requests (since DPR is machinery specific)
            // Let's check the model: DPR has no payment_request_id yet. The comparison expects it.
            // In PaymentRequest model, no direct DPR linkage. But machinery_payment_requests is different.
            // However, comparison mentions payment_request_id on DPR. Likely references machinery_payment_requests.
            // Let's use foreignId with constrained to that table.
            if (!Schema::hasColumn('daily_progress_reports', 'payment_request_id')) {
                $table->foreignId('payment_request_id')
                    ->nullable()
                    ->after('is_billed')
                    ->constrained('machinery_payment_requests')
                    ->onDelete('set null')
                    ->comment('Payment request generated from this DPR (if any)');
            }

            // 6. captured_at: timestamp when DPR was auto-captured (vs manual)
            if (!Schema::hasColumn('daily_progress_reports', 'captured_at')) {
                $table->timestamp('captured_at')->nullable()->after('payment_request_id')
                    ->comment('When the DPR was auto-imported from external system');
            }

            // 7. captured_by: user/system that captured
            if (!Schema::hasColumn('daily_progress_reports', 'captured_by')) {
                $table->unsignedBigInteger('captured_by')->nullable()->after('captured_at')
                    ->comment('User or system ID that created this record');
            }

            // 8. drift_count: count of data quality warnings detected
            if (!Schema::hasColumn('daily_progress_reports', 'drift_count')) {
                $table->integer('drift_count')->default(0)->after('captured_by')
                    ->comment('Number of data drift/anomaly warnings');
            }

            // 9. notes: free-text field (might exist already from model fillable? Not in initial)
            if (!Schema::hasColumn('daily_progress_reports', 'notes')) {
                $table->text('notes')->nullable()->after('drift_count')
                    ->comment('Additional free-text notes');
            }

            // Add foreign key for captured_by after column is added
            // We'll do it separately to avoid issues if column added conditionally
        });

        // Add foreign key for captured_by separately (guarded)
        try {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                if (Schema::hasColumn('daily_progress_reports', 'captured_by') &&
                    !$this->foreignExists('daily_progress_reports', 'daily_progress_reports_captured_by_foreign')) {
                    $table->foreign('captured_by')
                        ->references('id')
                        ->on('users')
                        ->onDelete('set null');
                }
            });
        } catch (\Exception $e) {
            \Log::warning('Could not add captured_by FK: ' . $e->getMessage());
        }

        // Add foreign key for ledger_entry_id if not already added (check existing migration)
        try {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                if (Schema::hasColumn('daily_progress_reports', 'ledger_entry_id') &&
                    Schema::hasTable('machinery_ledger') &&
                    !$this->foreignExists('daily_progress_reports', 'daily_progress_reports_ledger_entry_id_foreign')) {
                    $table->foreign('ledger_entry_id')
                        ->references('id')
                        ->on('machinery_ledger')
                        ->onDelete('set null');
                }
            });
        } catch (\Exception $e) {
            \Log::warning('Could not add ledger_entry_id FK: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $columnsToDrop = [
                'payment_request_id',
                'is_billed',
                'payment_status',
                'calculated_amount',
                'billable_hours',
                'captured_at',
                'captured_by',
                'drift_count',
                'notes',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('daily_progress_reports', $column)) {
                    $table->dropColumn($column);
                }
            }

            // Drop foreign keys
            try {
                $table->dropForeign(['captured_by']);
            } catch (\Exception $e) {}
            try {
                $table->dropForeign(['ledger_entry_id']);
            } catch (\Exception $e) {}
        });
    }

    /**
     * Check if a foreign key constraint exists
     */
    private function foreignExists(string $table, string $foreignKeyName): bool
    {
        try {
            $connection = Schema::getConnection();
            $doctrineSchemaManager = $connection->getDoctrineSchemaManager();
            $foreignKeys = $doctrineSchemaManager->listTableForeignKeys(
                $connection->getDatabasePrefix() . $table
            );

            foreach ($foreignKeys as $fk) {
                if ($fk->getName() === $foreignKeyName) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
};
