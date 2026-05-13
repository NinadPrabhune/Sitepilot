<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add unique constraints for (site_id, number) per module to prevent duplicate numbers within the same site.
     * This ensures per-site numbering uniqueness while allowing the same number across different sites.
     */
    public function up(): void
    {
        // Purchase Orders: unique (site_id, po_number)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unique(['site_id', 'po_number'], 'unique_po_number_per_site');
        });

        // Indents: unique (site_id, indent_number)
        Schema::table('indents', function (Blueprint $table) {
            $table->unique(['site_id', 'indent_number'], 'unique_indent_number_per_site');
        });

        // GRNs: unique (site_id, grn_number)
        Schema::table('grns', function (Blueprint $table) {
            $table->unique(['site_id', 'grn_number'], 'unique_grn_number_per_site');
        });

        // Purchase Invoices: unique (site_id, invoice_number)
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unique(['site_id', 'invoice_number'], 'unique_invoice_number_per_site');
        });

        // Payments Module: unique (site_id, payment_number)
        Schema::table('payments_module', function (Blueprint $table) {
            $table->unique(['site_id', 'payment_number'], 'unique_payment_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add safety checks before dropping unique constraints
        if (Schema::hasTable('purchase_orders') && Schema::hasIndex('purchase_orders', 'unique_po_number_per_site')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropUnique('unique_po_number_per_site');
            });
        }

        if (Schema::hasTable('indents') && Schema::hasIndex('indents', 'unique_indent_number_per_site')) {
            Schema::table('indents', function (Blueprint $table) {
                $table->dropUnique('unique_indent_number_per_site');
            });
        }

        if (Schema::hasTable('grns') && Schema::hasIndex('grns', 'unique_grn_number_per_site')) {
            Schema::table('grns', function (Blueprint $table) {
                $table->dropUnique('unique_grn_number_per_site');
            });
        }

        if (Schema::hasTable('purchase_invoices') && Schema::hasIndex('purchase_invoices', 'unique_invoice_number_per_site')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropUnique('unique_invoice_number_per_site');
            });
        }

        if (Schema::hasTable('payments_module') && Schema::hasIndex('payments_module', 'unique_payment_number_per_site')) {
            Schema::table('payments_module', function (Blueprint $table) {
                $table->dropUnique('unique_payment_number_per_site');
            });
        }
    }
};
