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
        // Ensure all number columns can accommodate prefix + padding
        // Max prefix (20) + max padding (10) = 30 chars minimum
        // Use 50 for safety margin
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_number', 50)->change();
        });

        Schema::table('indents', function (Blueprint $table) {
            $table->string('indent_number', 50)->change();
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->string('grn_number', 50)->change();
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('invoice_number', 50)->change();
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->string('payment_number', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_number')->change();
        });

        Schema::table('indents', function (Blueprint $table) {
            $table->string('indent_number')->change();
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->string('grn_number')->change();
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('invoice_number')->change();
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->string('payment_number')->change();
        });
    }
};
