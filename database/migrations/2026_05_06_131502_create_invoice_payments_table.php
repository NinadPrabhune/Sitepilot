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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('invoice_id');
            $table->date('date');
            $table->double('amount')->default(0);
            $table->integer('account_id')->nullable();
            $table->integer('payment_method');
            $table->string('reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('order_id', 255)->nullable();
            $table->string('currency', 255)->nullable();
            $table->string('txn_id', 255)->nullable();
            $table->string('payment_type', 255)->default('Manually');
            $table->string('receipt', 255)->nullable();
            $table->string('add_receipt', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
