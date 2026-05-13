<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove old single-column unique constraints on number fields that conflict with per-site/workspace numbering.
     * The new composite constraints (site_id/workspace_id, number) should be used instead.
     */
    public function up(): void
    {
        // Purchase Orders: remove old single-column unique if it exists
        if (Schema::hasIndex('purchase_orders', 'purchase_orders_po_number_unique')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropUnique('purchase_orders_po_number_unique');
            });
        }

        // GRNs: remove old single-column unique if it exists
        if (Schema::hasIndex('grns', 'grns_grn_number_unique')) {
            Schema::table('grns', function (Blueprint $table) {
                $table->dropUnique('grns_grn_number_unique');
            });
        }

        // Purchase Invoices: remove old single-column unique if it exists
        if (Schema::hasIndex('purchase_invoices', 'purchase_invoices_invoice_number_unique')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropUnique('purchase_invoices_invoice_number_unique');
            });
        }

        // Payments Module: remove old single-column unique if it exists
        if (Schema::hasIndex('payments_module', 'payments_module_payment_number_unique')) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->dropUnique('payments_module_payment_number_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add old single-column unique constraints (not recommended)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unique('po_number', 'purchase_orders_po_number_unique');
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->unique('grn_number', 'grns_grn_number_unique');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unique('invoice_number', 'purchase_invoices_invoice_number_unique');
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->unique('payment_number', 'payments_module_payment_number_unique');
        });
    }
};
