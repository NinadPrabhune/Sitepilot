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
        Schema::create('grn_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('grn_id')->index('grn_items_grn_id_foreign');
            $table->unsignedBigInteger('po_item_id')->nullable()->index('grn_items_po_item_id_foreign');
            $table->unsignedBigInteger('material_id')->index('grn_items_material_id_foreign');
            $table->decimal('ordered_qty', 15);
            $table->decimal('received_qty', 15);
            $table->decimal('accepted_qty', 15);
            $table->decimal('rejected_qty', 15);
            $table->decimal('price', 15);
            $table->decimal('tax_amount', 15);
            $table->decimal('subtotal', 15);
            $table->unsignedBigInteger('gst_master_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grn_items');
    }
};
