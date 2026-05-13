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
        Schema::create('pos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pos_id')->default(0);
            $table->unsignedBigInteger('customer_id')->default(0);
            $table->integer('warehouse_id')->default(0);
            $table->date('pos_date')->nullable();
            $table->integer('category_id')->default(0);
            $table->integer('status')->default(0);
            $table->integer('shipping_display')->default(1);
            $table->integer('workspace')->nullable();
            $table->integer('created_by')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos');
    }
};
