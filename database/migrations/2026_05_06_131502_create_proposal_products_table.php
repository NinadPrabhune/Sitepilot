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
        Schema::create('proposal_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('proposal_id');
            $table->string('product_type', 255)->nullable();
            $table->integer('product_id');
            $table->integer('quantity');
            $table->string('tax', 255)->nullable();
            $table->double('discount')->default(0);
            $table->double('price')->default(0);
            $table->longText('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_products');
    }
};
