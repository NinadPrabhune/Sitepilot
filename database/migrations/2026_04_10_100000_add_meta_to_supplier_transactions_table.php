<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds meta JSON column for storing payment_subtype (advance/invoice_payment) and non_accounting flags (for PO entries)
     */
    public function up(): void
    {
        if (Schema::hasTable('supplier_transactions')) {
            if (!Schema::hasColumn('supplier_transactions', 'meta')) {
                Schema::table('supplier_transactions', function (Blueprint $table) {
                    $table->json('meta')->nullable()->after('reference_amount');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_transactions')) {
            if (Schema::hasColumn('supplier_transactions', 'meta')) {
                Schema::table('supplier_transactions', function (Blueprint $table) {
                    $table->dropColumn('meta');
                });
            }
        }
    }
};