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
        // Critical for performance at scale (50k+ records)
        Schema::table('indents', function (Blueprint $table) {
            if (!Schema::hasIndex('indents', 'idx_indent_scope')) {
                $table->index(['site_id', 'id'], 'idx_indent_scope');
            }
            // CRITICAL: Add unique constraint for site-scoped numbering
            if (!Schema::hasIndex('indents', 'unique_indent_number_per_site')) {
                $table->unique(['site_id', 'indent_number'], 'unique_indent_number_per_site');
            }
        });

        Schema::table('grns', function (Blueprint $table) {
            if (!Schema::hasIndex('grns', 'idx_grn_scope')) {
                $table->index(['site_id', 'id'], 'idx_grn_scope');
            }
            if (!Schema::hasIndex('grns', 'unique_grn_number_per_site')) {
                $table->unique(['site_id', 'grn_number'], 'unique_grn_number_per_site');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (!Schema::hasIndex('purchase_invoices', 'idx_invoice_scope')) {
                $table->index(['site_id', 'id'], 'idx_invoice_scope');
            }
            if (!Schema::hasIndex('purchase_invoices', 'unique_invoice_number_per_site')) {
                $table->unique(['site_id', 'invoice_number'], 'unique_invoice_number_per_site');
            }
        });

        Schema::table('payments_module', function (Blueprint $table) {
            if (!Schema::hasIndex('payments_module', 'idx_payment_scope')) {
                $table->index(['site_id', 'id'], 'idx_payment_scope');
            }
            if (!Schema::hasIndex('payments_module', 'unique_payment_number_per_site')) {
                $table->unique(['site_id', 'payment_number'], 'unique_payment_number_per_site');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indents', function (Blueprint $table) {
            $table->dropIndex('idx_indent_scope');
            $table->dropUnique('unique_indent_number_per_site');
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->dropIndex('idx_grn_scope');
            $table->dropUnique('unique_grn_number_per_site');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_scope');
            $table->dropUnique('unique_invoice_number_per_site');
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->dropIndex('idx_payment_scope');
            $table->dropUnique('unique_payment_number_per_site');
        });
    }
};
