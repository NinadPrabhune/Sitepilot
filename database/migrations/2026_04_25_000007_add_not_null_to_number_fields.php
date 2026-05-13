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
        Schema::table('indents', function (Blueprint $table) {
            $table->string('indent_number')->nullable(false)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_number')->nullable(false)->change();
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->string('grn_number')->nullable(false)->change();
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable(false)->change();
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->string('payment_number')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indents', function (Blueprint $table) {
            $table->string('indent_number')->nullable()->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_number')->nullable()->change();
        });

        Schema::table('grns', function (Blueprint $table) {
            $table->string('grn_number')->nullable()->change();
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->change();
        });

        Schema::table('payments_module', function (Blueprint $table) {
            $table->string('payment_number')->nullable()->change();
        });
    }
};
