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
        Schema::create('receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billable_id');
            $table->string('billable_type', 255);
            $table->unsignedBigInteger('paddle_subscription_id')->nullable()->index();
            $table->string('checkout_id', 255);
            $table->string('order_id', 255)->unique();
            $table->string('amount', 255);
            $table->string('tax', 255);
            $table->string('currency', 3);
            $table->integer('quantity');
            $table->string('receipt_url', 255)->unique();
            $table->timestamp('paid_at')->useCurrentOnUpdate()->useCurrent();
            $table->timestamps();

            $table->index(['billable_id', 'billable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
