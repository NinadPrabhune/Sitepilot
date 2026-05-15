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
        Schema::create('bills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('bill_id', 255)->default('0');
            $table->integer('vendor_id');
            $table->integer('user_id')->nullable();
            $table->string('account_type', 255)->default('Accounting');
            $table->date('bill_date');
            $table->date('due_date');
            $table->integer('order_number')->default(0);
            $table->integer('status')->default(0);
            $table->integer('bill_shipping_display')->default(1);
            $table->date('send_date')->nullable();
            $table->integer('discount_apply')->default(0);
            $table->string('bill_module', 255)->default('account');
            $table->integer('category_id');
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
        Schema::dropIfExists('bills');
    }
};
