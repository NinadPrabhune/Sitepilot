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
        Schema::create('product_services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('sku', 255);
            $table->float('sale_price')->default(0);
            $table->float('purchase_price')->default(0);
            $table->integer('quantity')->default(0);
            $table->string('tax_id', 255)->nullable();
            $table->integer('category_id')->default(0);
            $table->longText('image')->nullable();
            $table->integer('unit_id')->default(0);
            $table->integer('sale_chartaccount_id')->default(0);
            $table->integer('expense_chartaccount_id')->default(0);
            $table->string('type', 255);
            $table->integer('warehouse_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('created_by')->default(0);
            $table->integer('workspace_id')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_services');
    }
};
