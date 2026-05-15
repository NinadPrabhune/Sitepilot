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
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('purchase_id');
            $table->date('date');
            $table->double('amount')->default(0);
            $table->integer('account_id')->nullable();
            $table->integer('payment_method');
            $table->string('reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('add_receipt', 255)->nullable();
            $table->integer('workspace')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
