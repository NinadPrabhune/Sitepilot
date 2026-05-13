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
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            // Add GRN item reference
            $table->unsignedBigInteger('grn_item_id')->nullable()->after('material_id');
            $table->foreign('grn_item_id')->references('id')->on('grn_items')->onDelete('set null');
            
            // Add GST master reference
            $table->unsignedBigInteger('gst_master_id')->nullable()->after('grn_item_id');
            $table->foreign('gst_master_id')->references('id')->on('gst_masters')->onDelete('set null');
            
            // Add discount and tax amounts
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('price');
            $table->decimal('tax_amount', 10, 2)->default(0.00)->after('discount_amount');
            
            // Update quantity and price to be decimal
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('price', 12, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['grn_item_id']);
            $table->dropForeign(['gst_master_id']);
            $table->dropColumn([
                'grn_item_id',
                'gst_master_id',
                'discount_amount',
                'tax_amount',
            ]);
            
            // Revert to integer
            $table->integer('quantity')->change();
        });
    }
};
