<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Source tracking
            if (!Schema::hasColumn('machinery_ledgers', 'source_type')) {
                $table->string('source_type', 30)
                      ->default('activity')
                      ->after('reference_id')
                      ->comment('Source flow of ledger entry')
                      ->index();
            }
            
            if (!Schema::hasColumn('machinery_ledgers', 'entry_source')) {
                $table->string('entry_source', 30)->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('machinery_ledgers', 'entry_source_id')) {
                $table->unsignedBigInteger('entry_source_id')->nullable()->after('entry_source');
            }
            
            // Settlement & reversal tracking
            if (!Schema::hasColumn('machinery_ledgers', 'is_settled')) {
                $table->boolean('is_settled')->default(false);
            }
            if (!Schema::hasColumn('machinery_ledgers', 'is_reversed')) {
                $table->boolean('is_reversed')->default(false);
            }
            if (!Schema::hasColumn('machinery_ledgers', 'reversal_reference_id')) {
                $table->unsignedBigInteger('reversal_reference_id')->nullable();
            }
            
            // Strong idempotency key
            if (!Schema::hasColumn('machinery_ledgers', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->unique();
            }
            
            // Calculation snapshot for audit
            if (!Schema::hasColumn('machinery_ledgers', 'calculation_snapshot')) {
                $table->json('calculation_snapshot')->nullable();
            }
            
            // Future-proof: cost center
            if (!Schema::hasColumn('machinery_ledgers', 'cost_center')) {
                $table->string('cost_center', 50)->nullable()->index();
            }
            
            // Site ID for consistency
            if (!Schema::hasColumn('machinery_ledgers', 'site_id')) {
                $table->unsignedBigInteger('site_id')->nullable();
            }
        });

        // Add indexes if they don't exist (SQLite doesn't support hasIndex, so we wrap in try-catch)
        try {
            Schema::table('machinery_ledgers', function (Blueprint $table) {
                // Idempotency constraint
                $table->unique([
                    'reference_type',
                    'reference_id',
                    'entry_type',
                    'entry_source'
                ], 'ledger_unique_entry');
                
                // Performance indexes
                $table->index(['date', 'machinery_id'], 'ledger_date_machine_idx');
                $table->index(['reference_type', 'reference_id'], 'ledger_ref_type_id_idx');
                $table->index(['machinery_id', 'is_settled', 'is_reversed', 'payment_request_id'], 'ledger_payment_lookup');
                $table->index(['source_type', 'date'], 'ledger_source_date_idx');
            });
        } catch (\Exception $e) {
            // Index may already exist, ignore error
        }

        // Backfill existing data
        DB::statement("UPDATE machinery_ledgers SET source_type = 'activity' WHERE source_type IS NULL OR source_type = ''");
        DB::statement("UPDATE machinery_ledgers SET is_settled = false WHERE is_settled IS NULL");
        DB::statement("UPDATE machinery_ledgers SET is_reversed = false WHERE is_reversed IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machinery_ledgers', function (Blueprint $table) {
            // Try to drop indexes (may not exist)
            try {
                $table->dropIndex('ledger_unique_entry');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('ledger_date_machine_idx');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('ledger_ref_type_id_idx');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('ledger_payment_lookup');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('ledger_source_date_idx');
            } catch (\Exception $e) {}
            try {
                $table->dropUnique(['idempotency_key']);
            } catch (\Exception $e) {}
            try {
                $table->dropIndex(['source_type']);
            } catch (\Exception $e) {}
            try {
                $table->dropIndex(['cost_center']);
            } catch (\Exception $e) {}
            
            // Drop columns if they exist
            if (Schema::hasColumn('machinery_ledgers', 'source_type')) {
                $table->dropColumn('source_type');
            }
            if (Schema::hasColumn('machinery_ledgers', 'entry_source')) {
                $table->dropColumn('entry_source');
            }
            if (Schema::hasColumn('machinery_ledgers', 'entry_source_id')) {
                $table->dropColumn('entry_source_id');
            }
            if (Schema::hasColumn('machinery_ledgers', 'is_settled')) {
                $table->dropColumn('is_settled');
            }
            if (Schema::hasColumn('machinery_ledgers', 'is_reversed')) {
                $table->dropColumn('is_reversed');
            }
            if (Schema::hasColumn('machinery_ledgers', 'reversal_reference_id')) {
                $table->dropColumn('reversal_reference_id');
            }
            if (Schema::hasColumn('machinery_ledgers', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('machinery_ledgers', 'calculation_snapshot')) {
                $table->dropColumn('calculation_snapshot');
            }
            if (Schema::hasColumn('machinery_ledgers', 'cost_center')) {
                $table->dropColumn('cost_center');
            }
            if (Schema::hasColumn('machinery_ledgers', 'site_id')) {
                $table->dropColumn('site_id');
            }
        });
    }
};
