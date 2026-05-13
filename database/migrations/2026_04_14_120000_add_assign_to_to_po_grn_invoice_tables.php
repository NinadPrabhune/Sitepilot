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
        Schema::table('purchase_orders', function (Blueprint $table) {
            // NOTE: assign_to is comma-separated. Not indexed. Consider pivot table for scaling.
            if (!Schema::hasColumn('purchase_orders', 'assign_to')) {
                $table->text('assign_to')->nullable()->after('description');
            }
        });

        Schema::table('grns', function (Blueprint $table) {
            // NOTE: assign_to is comma-separated. Not indexed. Consider pivot table for scaling.
            if (!Schema::hasColumn('grns', 'assign_to')) {
                $table->text('assign_to')->nullable()->after('remarks');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            // NOTE: assign_to is comma-separated. Not indexed. Consider pivot table for scaling.
            if (!Schema::hasColumn('purchase_invoices', 'assign_to')) {
                $table->text('assign_to')->nullable()->after('invoice_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('assign_to');
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->dropColumn('assign_to');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn('assign_to');
        });
    }
};
