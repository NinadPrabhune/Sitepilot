<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE purchase_order_items SET 
                quantity = ROUND(quantity, 2),
                received_qty = ROUND(received_qty, 2),
                price = ROUND(price, 2),
                tax_amount = ROUND(tax_amount, 2),
                discount_amount = ROUND(discount_amount, 2),
                subtotal = ROUND(subtotal, 2),
                indent_quantity = ROUND(indent_quantity, 2)
        ");

        DB::statement("
            UPDATE grn_items SET 
                ordered_qty = ROUND(ordered_qty, 2),
                received_qty = ROUND(received_qty, 2),
                accepted_qty = ROUND(accepted_qty, 2),
                rejected_qty = ROUND(rejected_qty, 2),
                price = ROUND(price, 2),
                tax_amount = ROUND(tax_amount, 2),
                subtotal = ROUND(subtotal, 2)
        ");

        DB::statement("
            UPDATE purchase_invoice_items SET 
                quantity = ROUND(quantity, 2),
                price = ROUND(price, 2),
                discount_amount = ROUND(discount_amount, 2),
                tax_amount = ROUND(tax_amount, 2),
                subtotal = ROUND(subtotal, 2)
        ");

        DB::statement("
            UPDATE indent_items SET 
                quantity = ROUND(quantity, 2),
                price = ROUND(price, 2),
                subtotal = ROUND(subtotal, 2)
        ");

        DB::statement("
            UPDATE material_issue_items SET 
                quantity = ROUND(quantity, 2),
                rate = ROUND(rate, 2),
                amount = ROUND(amount, 2)
        ");

        DB::statement("
            UPDATE material_return_items SET 
                quantity = ROUND(quantity, 2)
        ");

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('received_qty', 15, 2)->change();
            $table->decimal('price', 15, 2)->change();
            $table->decimal('tax_amount', 15, 2)->change();
            $table->decimal('discount_amount', 15, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
            $table->decimal('indent_quantity', 15, 2)->change();
        });

        Schema::table('grn_items', function (Blueprint $table) {
            $table->decimal('ordered_qty', 15, 2)->change();
            $table->decimal('received_qty', 15, 2)->change();
            $table->decimal('accepted_qty', 15, 2)->change();
            $table->decimal('rejected_qty', 15, 2)->change();
            $table->decimal('price', 15, 2)->change();
            $table->decimal('tax_amount', 15, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('price', 15, 2)->change();
            $table->decimal('discount_amount', 15, 2)->change();
            $table->decimal('tax_amount', 15, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
        });

        Schema::table('indent_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('price', 15, 2)->change();
            $table->decimal('subtotal', 15, 2)->change();
        });

        Schema::table('material_issue_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('rate', 15, 2)->change();
            $table->decimal('amount', 15, 2)->change();
        });

        Schema::table('material_return_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
            $table->decimal('received_qty', 15, 3)->change();
            $table->decimal('indent_quantity', 15, 3)->change();
        });

        Schema::table('grn_items', function (Blueprint $table) {
            $table->decimal('ordered_qty', 15, 3)->change();
            $table->decimal('received_qty', 15, 3)->change();
            $table->decimal('accepted_qty', 15, 3)->change();
            $table->decimal('rejected_qty', 15, 3)->change();
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
        });
    }
};
