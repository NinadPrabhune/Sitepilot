<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        // Add composite index for supplier_id + site_id (most common query pattern)
        if (!Schema::hasIndex('supplier_transactions', 'supplier_transactions_supplier_site_index')) {
            Schema::table('supplier_transactions', function (Blueprint $table) {
                $table->index(['supplier_id', 'site_id'], 'supplier_transactions_supplier_site_index');
            });
        }

        // Add index on reference_type for filtering
        if (!Schema::hasIndex('supplier_transactions', 'supplier_transactions_reference_type_index')) {
            Schema::table('supplier_transactions', function (Blueprint $table) {
                $table->index('reference_type', 'supplier_transactions_reference_type_index');
            });
        }

        // Add index on transaction_date for date range queries
        if (!Schema::hasIndex('supplier_transactions', 'supplier_transactions_date_index')) {
            Schema::table('supplier_transactions', function (Blueprint $table) {
                $table->index('transaction_date', 'supplier_transactions_date_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('supplier_transactions')) {
            return;
        }

        Schema::table('supplier_transactions', function (Blueprint $table) {
            $table->dropIndex('supplier_transactions_supplier_site_index');
            $table->dropIndex('supplier_transactions_reference_type_index');
            $table->dropIndex('supplier_transactions_date_index');
        });
    }
};