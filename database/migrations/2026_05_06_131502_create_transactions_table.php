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
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable();
            $table->string('vendor_name', 255)->nullable();
            $table->string('user_type', 255);
            $table->integer('account');
            $table->string('type', 255);
            $table->double('amount')->default(0);
            $table->longText('description')->nullable();
            $table->date('date');
            $table->integer('payment_id')->default(0);
            $table->string('category', 255);
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
        Schema::dropIfExists('transactions');
    }
};
