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
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('customer_id');
            $table->integer('user_id')->nullable();
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('contact', 255)->nullable();
            $table->string('tax_number', 255)->nullable();
            $table->string('billing_name', 255);
            $table->string('billing_country', 255);
            $table->string('billing_state', 255);
            $table->string('billing_city', 255);
            $table->string('billing_phone', 255);
            $table->string('billing_zip', 255);
            $table->text('billing_address');
            $table->string('shipping_name', 255)->nullable();
            $table->string('shipping_country', 255)->nullable();
            $table->string('shipping_state', 255)->nullable();
            $table->string('shipping_city', 255)->nullable();
            $table->string('shipping_phone', 255)->nullable();
            $table->string('shipping_zip', 255)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('lang', 255)->default('en');
            $table->double('balance')->default(0);
            $table->string('credit_note_balance', 255)->default('0.00');
            $table->integer('workspace')->nullable();
            $table->integer('created_by')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
