<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add to purchase_orders
        if (Schema::hasTable('purchase_orders') && !Schema::hasColumn('purchase_orders', 'idempotency_key')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique();
            });
        }

        // Add to grns
        if (Schema::hasTable('grns') && !Schema::hasColumn('grns', 'idempotency_key')) {
            Schema::table('grns', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique();
            });
        }

        // Add to purchase_invoices
        if (Schema::hasTable('purchase_invoices') && !Schema::hasColumn('purchase_invoices', 'idempotency_key')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        // Add safety checks for purchase_orders
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                // Drop unique constraint only if it exists
                if (Schema::hasIndex('purchase_orders', 'purchase_orders_idempotency_key_unique')) {
                    $table->dropUnique('purchase_orders_idempotency_key_unique');
                }
                // Drop column only if it exists
                if (Schema::hasColumn('purchase_orders', 'idempotency_key')) {
                    $table->dropColumn('idempotency_key');
                }
            });
        }

        // Add safety checks for grns
        if (Schema::hasTable('grns')) {
            Schema::table('grns', function (Blueprint $table) {
                // Drop unique constraint only if it exists
                if (Schema::hasIndex('grns', 'grns_idempotency_key_unique')) {
                    $table->dropUnique('grns_idempotency_key_unique');
                }
                // Drop column only if it exists
                if (Schema::hasColumn('grns', 'idempotency_key')) {
                    $table->dropColumn('idempotency_key');
                }
            });
        }

        // Add safety checks for purchase_invoices
        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                // Drop unique constraint only if it exists
                if (Schema::hasIndex('purchase_invoices', 'purchase_invoices_idempotency_key_unique')) {
                    $table->dropUnique('purchase_invoices_idempotency_key_unique');
                }
                // Drop column only if it exists
                if (Schema::hasColumn('purchase_invoices', 'idempotency_key')) {
                    $table->dropColumn('idempotency_key');
                }
            });
        }
    }
};
