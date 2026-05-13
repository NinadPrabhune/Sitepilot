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
        // CRITICAL: Scope-specific idempotency to prevent cross-scope conflicts
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable();
                if (Schema::hasColumn('purchase_orders', 'workspace_id')) {
                    $table->unique(['idempotency_key', 'workspace_id'], 'unique_po_idempotency');
                }
            }
        });

        Schema::table('grns', function (Blueprint $table) {
            if (!Schema::hasColumn('grns', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable();
                if (Schema::hasColumn('grns', 'site_id')) {
                    $table->unique(['idempotency_key', 'site_id'], 'unique_grn_idempotency');
                }
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_invoices', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable();
                if (Schema::hasColumn('purchase_invoices', 'site_id')) {
                    $table->unique(['idempotency_key', 'site_id'], 'unique_invoice_idempotency');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'idempotency_key')) {
                // Drop unique constraint only if it exists
                if (Schema::hasIndex('purchase_orders', 'unique_po_idempotency')) {
                    $table->dropUnique('unique_po_idempotency');
                }
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::table('grns', function (Blueprint $table) {
            if (Schema::hasColumn('grns', 'idempotency_key')) {
                // Drop unique constraint only if it exists
                if (Schema::hasIndex('grns', 'unique_grn_idempotency')) {
                    $table->dropUnique('unique_grn_idempotency');
                }
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_invoices', 'idempotency_key')) {
                // Drop unique constraint only if it exists
                if (Schema::hasIndex('purchase_invoices', 'unique_invoice_idempotency')) {
                    $table->dropUnique('unique_invoice_idempotency');
                }
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
