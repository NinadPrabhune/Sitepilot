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
        Schema::create('invoice_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('invoice_id');
            $table->string('product_type', 255)->nullable();
            $table->integer('product_id');
            $table->integer('quantity');
            $table->string('tax', 255)->nullable();
            $table->double('discount')->default(0);
            $table->longText('description')->nullable();
            $table->double('price')->default(0);
            $table->timestamps();
            $table->string('product_name', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_products');
    }
};
