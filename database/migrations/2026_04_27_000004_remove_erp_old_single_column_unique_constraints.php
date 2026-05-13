<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove old single-column unique constraints from ERP tables that conflict with per-site/workspace numbering.
     */
    public function up(): void
    {
        // Supplier Advances: remove old single-column unique if it exists
        if (Schema::hasTable('supplier_advances') && Schema::hasIndex('supplier_advances', 'supplier_advances_advance_number_unique')) {
            Schema::table('supplier_advances', function (Blueprint $table) {
                $table->dropUnique('supplier_advances_advance_number_unique');
            });
        }

        // Material Issues: remove old single-column unique if it exists
        if (Schema::hasTable('material_issues') && Schema::hasIndex('material_issues', 'material_issues_issue_number_unique')) {
            Schema::table('material_issues', function (Blueprint $table) {
                $table->dropUnique('material_issues_issue_number_unique');
            });
        }

        // Material Returns: remove old single-column unique if it exists
        if (Schema::hasTable('material_returns') && Schema::hasIndex('material_returns', 'material_returns_return_number_unique')) {
            Schema::table('material_returns', function (Blueprint $table) {
                $table->dropUnique('material_returns_return_number_unique');
            });
        }

        // Material Transfers: remove old single-column unique if it exists
        if (Schema::hasTable('material_transfers') && Schema::hasIndex('material_transfers', 'material_transfers_record_number_unique')) {
            Schema::table('material_transfers', function (Blueprint $table) {
                $table->dropUnique('material_transfers_record_number_unique');
            });
        }

        // Daily Consumption: remove old single-column unique if it exists
        if (Schema::hasTable('daily_consumption_masters') && Schema::hasIndex('daily_consumption_masters', 'daily_consumption_masters_consumption_number_unique')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->dropUnique('daily_consumption_masters_consumption_number_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add old single-column unique constraints (not recommended)
        // Add safety checks to prevent duplicate key errors
        if (Schema::hasTable('supplier_advances') && !Schema::hasIndex('supplier_advances', 'supplier_advances_advance_number_unique')) {
            Schema::table('supplier_advances', function (Blueprint $table) {
                $table->unique('advance_number', 'supplier_advances_advance_number_unique');
            });
        }

        if (Schema::hasTable('material_issues') && !Schema::hasIndex('material_issues', 'material_issues_issue_number_unique')) {
            Schema::table('material_issues', function (Blueprint $table) {
                $table->unique('issue_number', 'material_issues_issue_number_unique');
            });
        }

        if (Schema::hasTable('material_returns') && !Schema::hasIndex('material_returns', 'material_returns_return_number_unique')) {
            Schema::table('material_returns', function (Blueprint $table) {
                $table->unique('return_number', 'material_returns_return_number_unique');
            });
        }

        if (Schema::hasTable('material_transfers') && !Schema::hasIndex('material_transfers', 'material_transfers_record_number_unique')) {
            Schema::table('material_transfers', function (Blueprint $table) {
                $table->unique('record_number', 'material_transfers_record_number_unique');
            });
        }

        if (Schema::hasTable('daily_consumption_masters') && !Schema::hasIndex('daily_consumption_masters', 'daily_consumption_masters_consumption_number_unique')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->unique('consumption_number', 'daily_consumption_masters_consumption_number_unique');
            });
        }
    }
};
