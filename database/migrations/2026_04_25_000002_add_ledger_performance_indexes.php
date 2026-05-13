<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        Schema::table('supplier_transactions', function (Blueprint $table) {
            // Composite index for balance recalculation queries - only if transaction_date column exists
            if (!Schema::hasIndex('supplier_transactions', 'idx_balance_recalc') && Schema::hasColumn('supplier_transactions', 'transaction_date')) {
                $table->index(['supplier_id', 'site_id', 'transaction_date'], 'idx_balance_recalc');
            }
            
            // Index for idempotency checks - only if columns exist
            if (!Schema::hasIndex('supplier_transactions', 'idx_idempotency') && 
                Schema::hasColumn('supplier_transactions', 'reference_type') && 
                Schema::hasColumn('supplier_transactions', 'reference_id')) {
                $table->index(['reference_type', 'reference_id'], 'idx_idempotency');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        Schema::table('supplier_transactions', function (Blueprint $table) {
            // Check if indexes exist before dropping them
            if (Schema::hasIndex('supplier_transactions', 'idx_balance_recalc')) {
                $table->dropIndex('idx_balance_recalc');
            }
            if (Schema::hasIndex('supplier_transactions', 'idx_idempotency')) {
                $table->dropIndex('idx_idempotency');
            }
        });
    }
};
