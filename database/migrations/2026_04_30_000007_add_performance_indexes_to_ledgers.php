<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Machinery Ledger indexes
        Schema::table('machinery_ledger', function (Blueprint $table) {
            // Composite index for common queries
            $table->index(['machinery_id', 'date'], 'idx_machinery_date');
            $table->index(['reference_type', 'reference_id'], 'idx_reference');
            $table->index(['is_reversal', 'date'], 'idx_reversal_date');
            $table->index(['is_locked', 'date'], 'idx_locked_date');
        });

        // Supplier Ledger indexes
        Schema::table('supplier_ledger', function (Blueprint $table) {
            // Composite index for common queries
            $table->index(['supplier_id', 'date'], 'idx_supplier_date');
            $table->index(['reference_type', 'reference_id'], 'idx_reference');
            $table->index(['is_reversal', 'date'], 'idx_reversal_date');
        });

        // System Health Logs indexes
        Schema::table('system_health_logs', function (Blueprint $table) {
            // Composite index for trend analysis
            $table->index(['workspace_id', 'created_at'], 'idx_workspace_created');
            $table->index(['health_status', 'created_at'], 'idx_status_created');
        });

        // Daily Progress Reports indexes
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->index(['machinery_id', 'date'], 'idx_machinery_date');
            $table->index(['site_id', 'date'], 'idx_site_date');
        });

        // Daily Consumption Masters indexes
        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->index(['machinery_id', 'consumption_date'], 'idx_machinery_date');
            $table->index(['site_id', 'consumption_date'], 'idx_site_date');
        });

        // Maintenance Logs indexes
        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->index(['machinery_id', 'maintenance_date'], 'idx_machinery_date');
            $table->index(['site_id', 'maintenance_date'], 'idx_site_date');
        });
    }

    public function down()
    {
        Schema::table('machinery_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_machinery_date');
            $table->dropIndex('idx_reference');
            $table->dropIndex('idx_reversal_date');
            $table->dropIndex('idx_locked_date');
        });

        Schema::table('supplier_ledger', function (Blueprint $table) {
            $table->dropIndex('idx_supplier_date');
            $table->dropIndex('idx_reference');
            $table->dropIndex('idx_reversal_date');
        });

        Schema::table('system_health_logs', function (Blueprint $table) {
            $table->dropIndex('idx_workspace_created');
            $table->dropIndex('idx_status_created');
        });

        Schema::table('daily_progress_reports', function (Blueprint $table) {
            $table->dropIndex('idx_machinery_date');
            $table->dropIndex('idx_site_date');
        });

        Schema::table('daily_consumption_masters', function (Blueprint $table) {
            $table->dropIndex('idx_machinery_date');
            $table->dropIndex('idx_site_date');
        });

        Schema::table('maintenance_logs', function (Blueprint $table) {
            $table->dropIndex('idx_machinery_date');
            $table->dropIndex('idx_site_date');
        });
    }
};
