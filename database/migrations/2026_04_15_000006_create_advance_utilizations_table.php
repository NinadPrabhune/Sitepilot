<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create advance_utilizations table to track how much of each advance
     * is used against which invoices
     */
    public function up(): void
    {
        Schema::create('advance_utilizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('utilized_amount', 15, 2);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_advance_id', 'purchase_invoice_id'], 'adv_util_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_utilizations');
    }
};
