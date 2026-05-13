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
        Schema::create('indent_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('indent_id')->index('indent_items_indent_id_foreign');
            $table->unsignedBigInteger('material_id')->index('indent_items_material_id_foreign');
            $table->decimal('quantity', 15);
            $table->string('unit');
            $table->decimal('price', 15);
            $table->decimal('subtotal', 15);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indent_items');
    }
};
