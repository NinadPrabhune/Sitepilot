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
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('purchase_invoice_id')->index('purchase_invoice_items_purchase_invoice_id_foreign');
            $table->unsignedBigInteger('material_id')->index('purchase_invoice_items_material_id_foreign');
            $table->unsignedBigInteger('grn_item_id')->nullable()->index('purchase_invoice_items_grn_item_id_foreign');
            $table->unsignedBigInteger('gst_master_id')->nullable()->index('purchase_invoice_items_gst_master_id_foreign');
            $table->decimal('quantity', 15);
            $table->string('unit')->nullable();
            $table->decimal('price', 15);
            $table->decimal('discount_amount', 15);
            $table->decimal('tax_amount', 15);
            $table->decimal('subtotal', 15);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
