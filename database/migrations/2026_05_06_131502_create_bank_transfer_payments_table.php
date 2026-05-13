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
        Schema::create('bank_transfer_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id', 255);
            $table->integer('user_id');
            $table->longText('request');
            $table->string('status', 255);
            $table->string('type', 255);
            $table->string('payment_type', 255)->default('Bank Transfer');
            $table->string('bank_accounts_id', 255)->default('0');
            $table->double('price')->default(0);
            $table->string('price_currency', 255)->default('USD');
            $table->string('attachment', 255)->nullable();
            $table->integer('created_by');
            $table->integer('workspace')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_payments');
    }
};
