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
        Schema::create('advance_adjustments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->decimal('utilized_amount', 15)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_invoice_id', 'deleted_at'], 'idx_advance_inv_deleted');
            $table->index(['payment_id', 'deleted_at'], 'idx_advance_payment_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_adjustments');
    }
};
