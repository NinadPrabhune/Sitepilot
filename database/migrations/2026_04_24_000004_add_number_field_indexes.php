<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add composite indexes on (site_id, number_field) for faster duplicate detection and reporting.
     * These indexes complement the existing (site_id, id DESC) indexes.
     */
    public function up(): void
    {
        // Purchase Orders: index on (site_id, po_number)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['site_id', 'po_number'], 'idx_po_site_id_number');
        });

        // Indents: index on (site_id, indent_number)
        Schema::table('indents', function (Blueprint $table) {
            $table->index(['site_id', 'indent_number'], 'idx_indent_site_id_number');
        });

        // GRNs: index on (site_id, grn_number)
        Schema::table('grns', function (Blueprint $table) {
            $table->index(['site_id', 'grn_number'], 'idx_grn_site_id_number');
        });

        // Purchase Invoices: index on (site_id, invoice_number)
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->index(['site_id', 'invoice_number'], 'idx_invoice_site_id_number');
        });

        // Payments Module: index on (site_id, payment_number)
        Schema::table('payments_module', function (Blueprint $table) {
            $table->index(['site_id', 'payment_number'], 'idx_payment_site_id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_site_id_number');
        });

        Schema::table('indents', function (Blueprint $table) {
            $table->dropIndex('idx_indent_site_id_number');
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->dropIndex('idx_grn_site_id_number');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_site_id_number');
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropIndex('idx_payment_site_id_number');
        });
    }
};
