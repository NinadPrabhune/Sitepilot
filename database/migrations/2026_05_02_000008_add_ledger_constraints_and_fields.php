<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add constraints and fields to machinery_ledger table.
     * Guards every change so it is safe to run even if the core
     * migration has not yet been applied.
     */
    public function up(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            // Add strict linkage fields only if they don't exist
            if (!Schema::hasColumn('machinery_ledger', 'dpr_id')) {
                $table->unsignedBigInteger('dpr_id')->nullable()->after('reference_id');
            }
            if (!Schema::hasColumn('machinery_ledger', 'payment_request_id')) {
                $table->unsignedBigInteger('payment_request_id')->nullable()->after('dpr_id');
            }
            if (!Schema::hasColumn('machinery_ledger', 'is_locked')) {
                $table->boolean('is_locked')->default(false);
            }
            if (!Schema::hasColumn('machinery_ledger', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
            if (!Schema::hasColumn('machinery_ledger', 'locked_by')) {
                $table->unsignedBigInteger('locked_by')->nullable();
            }

            // idempotency_key already added — guard for safety
            if (!Schema::hasColumn('machinery_ledger', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable();
            }

            // Add indexes only if they don't exist
            if (!Schema::hasIndex('machinery_ledger', 'idx_dpr_id')) {
                $table->index('dpr_id', 'idx_dpr_id');
            }
            if (!Schema::hasIndex('machinery_ledger', 'idx_payment_request_id')) {
                $table->index('payment_request_id', 'idx_payment_request_id');
            }
            if (!Schema::hasIndex('machinery_ledger', 'idx_is_reversal')) {
                $table->index('is_reversal', 'idx_is_reversal');
            }
            if (!Schema::hasIndex('machinery_ledger', 'idx_ledger_type')) {
                $table->index('ledger_type', 'idx_ledger_type');
            }
        });

        // Add foreign keys only if columns exist
        Schema::table('machinery_ledger', function (Blueprint $table) {
            if (Schema::hasColumn('machinery_ledger', 'dpr_id')) {
                try {
                    $table->foreign('dpr_id')->references('id')->on('daily_progress_reports');
                } catch (\Exception $e) {
                    // FK may already exist, continue
                }
            }
            if (Schema::hasColumn('machinery_ledger', 'payment_request_id')) {
                try {
                    $table->foreign('payment_request_id')->references('id')->on('machinery_payment_requests');
                } catch (\Exception $e) {
                    // FK may already exist, continue
                }
            }
            if (Schema::hasColumn('machinery_ledger', 'locked_by')) {
                try {
                    $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
                } catch (\Exception $e) {
                    // FK may already exist, continue
                }
            }
        });

        // Self-referential reversal chain FK (machinery_ledger already has reversed_entry_id)
        if (Schema::hasColumn('machinery_ledger', 'reversed_entry_id')) {
            try {
                $table = Schema::getConnection()->getSchemaBuilder()->getConnection();
                DB::statement(
                    'ALTER TABLE machinery_ledger ADD CONSTRAINT machinery_ledger_reversed_entry_foreign
                     FOREIGN KEY (reversed_entry_id) REFERENCES machinery_ledger(id) ON DELETE RESTRICT'
                );
            } catch (\Exception $e) {
                // Constraint may already exist, continue
            }
        }
    }

    public function down(): void
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            // Drop FK on daily_progress_reports
            try {
                $table->dropForeign(['dpr_id']);
            } catch (\Exception $e) {}
            // Drop FK on payment_request_id
            try {
                $table->dropForeign(['payment_request_id']);
            } catch (\Exception $e) {}
            // Drop FK on locked_by
            try {
                $table->dropForeign(['locked_by']);
            } catch (\Exception $e) {}

            // Drop indexes
            try { $table->dropIndex('idx_dpr_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_payment_request_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_is_reversal'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_ledger_type'); } catch (\Exception $e) {}
            try { $table->dropForeign(['reversed_entry_id']); } catch (\Exception $e) {}

            // Drop columns that this migration added (only if present)
            if (Schema::hasColumn('machinery_ledger', 'dpr_id')) {
                $table->dropColumn(['dpr_id']);
            }
            if (Schema::hasColumn('machinery_ledger', 'is_locked')) {
                $table->dropColumn(['is_locked']);
            }
            if (Schema::hasColumn('machinery_ledger', 'locked_at')) {
                $table->dropColumn(['locked_at']);
            }
            if (Schema::hasColumn('machinery_ledger', 'locked_by')) {
                $table->dropColumn(['locked_by']);
            }
        });
    }
};
