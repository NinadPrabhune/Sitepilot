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
        Schema::create('payment_module_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payment_module_id')->index('payment_module_allocations_payment_module_id_foreign');
            $table->unsignedBigInteger('purchase_invoice_id')->nullable()->index('payment_module_allocations_purchase_invoice_id_foreign');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index('payment_module_allocations_purchase_order_id_foreign');
            $table->decimal('allocated_amount', 15)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_module_allocations');
    }
};
