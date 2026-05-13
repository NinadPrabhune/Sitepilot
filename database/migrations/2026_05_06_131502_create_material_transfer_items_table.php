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
        Schema::create('material_transfer_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('material_transfer_id')->index('material_transfer_items_material_transfer_id_foreign');
            $table->unsignedBigInteger('material_id');
            $table->decimal('quantity', 10);
            $table->string('unit');
            $table->decimal('price', 10);
            $table->decimal('subtotal', 12);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_transfer_items');
    }
};
