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
        Schema::create('revenues', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');
            $table->float('amount')->default(0);
            $table->integer('account_id');
            $table->integer('customer_id');
            $table->integer('user_id');
            $table->integer('category_id');
            $table->integer('payment_method');
            $table->string('reference', 255);
            $table->longText('description');
            $table->string('add_receipt', 255)->nullable();
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
        Schema::dropIfExists('revenues');
    }
};
