<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->cascadeOnDelete()->after('supplier_id');
            $table->enum('payment_type', ['advance_against_po', 'against_invoice', 'mixed', 'on_account'])->default('against_invoice')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments_module', function (Blueprint $table) {
            if (Schema::hasColumn('payments_module', 'purchase_order_id')) {
                $table->dropForeign(['purchase_order_id']);
                $table->dropColumn('purchase_order_id');
            }
            $table->enum('payment_type', ['against_invoice', 'advance'])->default('against_invoice')->change();
        });
    }
};