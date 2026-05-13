<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add composite indexes for (site_id, id DESC) to optimize number generation queries.
     * This improves performance for getLastNumber() queries which filter by site_id and order by id DESC.
     * Without these indexes, queries become slow at scale as tables grow.
     */
    public function up(): void
    {
        // Purchase Orders: index on (site_id, id DESC)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['site_id', 'id'], 'idx_po_site_id_id');
        });

        // Indents: index on (site_id, id DESC)
        Schema::table('indents', function (Blueprint $table) {
            $table->index(['site_id', 'id'], 'idx_indent_site_id_id');
        });

        // GRNs: index on (site_id, id DESC)
        Schema::table('grns', function (Blueprint $table) {
            $table->index(['site_id', 'id'], 'idx_grn_site_id_id');
        });

        // Purchase Invoices: index on (site_id, id DESC)
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->index(['site_id', 'id'], 'idx_invoice_site_id_id');
        });

        // Payments Module: index on (site_id, id DESC)
        Schema::table('payments_module', function (Blueprint $table) {
            $table->index(['site_id', 'id'], 'idx_payment_site_id_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_site_id_id');
        });

        Schema::table('indents', function (Blueprint $table) {
            $table->dropIndex('idx_indent_site_id_id');
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->dropIndex('idx_grn_site_id_id');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_site_id_id');
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropIndex('idx_payment_site_id_id');
        });
    }
};
