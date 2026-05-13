<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add composite unique constraints for ERP tables to support per-site numbering.
     */
    public function up(): void
    {
        // Supplier Advances: unique (site_id, advance_number)
        Schema::table('supplier_advances', function (Blueprint $table) {
            $table->unique(['site_id', 'advance_number'], 'unique_advance_number_per_site');
        });

        // Material Issues: unique (site_id, issue_number)
        Schema::table('material_issues', function (Blueprint $table) {
            $table->unique(['site_id', 'issue_number'], 'unique_issue_number_per_site');
        });

        // Material Returns: unique (site_id, return_number)
        Schema::table('material_returns', function (Blueprint $table) {
            $table->unique(['site_id', 'return_number'], 'unique_return_number_per_site');
        });

        // Material Transfers: unique (workspace_id, record_number) - transfers are workspace-scoped
        Schema::table('material_transfers', function (Blueprint $table) {
            $table->unique(['workspace_id', 'record_number'], 'unique_transfer_number_per_workspace');
        });

        // Daily Consumption: unique (site_id, consumption_number)
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->unique(['site_id', 'consumption_number'], 'unique_consumption_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add table and index existence checks before dropping unique constraints
        if (Schema::hasTable('supplier_advances') && Schema::hasIndex('supplier_advances', 'unique_advance_number_per_site')) {
            Schema::table('supplier_advances', function (Blueprint $table) {
                $table->dropUnique('unique_advance_number_per_site');
            });
        }

        if (Schema::hasTable('material_issues') && Schema::hasIndex('material_issues', 'unique_issue_number_per_site')) {
            Schema::table('material_issues', function (Blueprint $table) {
                $table->dropUnique('unique_issue_number_per_site');
            });
        }

        if (Schema::hasTable('material_returns') && Schema::hasIndex('material_returns', 'unique_return_number_per_site')) {
            Schema::table('material_returns', function (Blueprint $table) {
                $table->dropUnique('unique_return_number_per_site');
            });
        }

        if (Schema::hasTable('material_transfers') && Schema::hasIndex('material_transfers', 'unique_transfer_number_per_workspace')) {
            Schema::table('material_transfers', function (Blueprint $table) {
                $table->dropUnique('unique_transfer_number_per_workspace');
            });
        }

        if (Schema::hasTable('daily_consumption_masters') && Schema::hasIndex('daily_consumption_masters', 'unique_consumption_number_per_site')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->dropUnique('unique_consumption_number_per_site');
            });
        }
    }
};
