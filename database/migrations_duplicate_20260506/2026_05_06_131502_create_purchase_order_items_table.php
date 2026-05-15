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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('purchase_order_id')->index('purchase_order_items_purchase_order_id_foreign');
            $table->unsignedBigInteger('material_id')->index('purchase_order_items_material_id_foreign');
            $table->decimal('quantity', 15);
            $table->decimal('received_qty', 15);
            $table->string('unit');
            $table->decimal('price', 15);
            $table->decimal('subtotal', 15);
            $table->decimal('indent_quantity', 15);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('gst_master_id')->nullable()->index('purchase_order_items_gst_master_id_foreign');
            $table->decimal('tax_amount', 15);
            $table->decimal('discount_amount', 15);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
