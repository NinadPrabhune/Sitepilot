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
        Schema::create('advance_utilizations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('transaction_flow_id', 50)->nullable()->index('idx_utilization_transaction_flow');
            $table->unsignedBigInteger('supplier_advance_id');
            $table->unsignedBigInteger('purchase_invoice_id');
            $table->unsignedBigInteger('payments_module_id')->nullable()->index('advance_utilizations_payments_module_id_foreign');
            $table->decimal('utilized_amount', 15);
            $table->enum('status', ['reserved', 'applied', 'reversed'])->default('applied')->index();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_advance_id', 'purchase_invoice_id'], 'adv_util_idx');
            $table->index(['supplier_advance_id', 'status', 'utilized_amount'], 'idx_advance_status_amount');
            $table->index(['transaction_flow_id', 'created_at'], 'idx_flow_created');
            $table->index(['purchase_invoice_id', 'status', 'utilized_amount'], 'idx_invoice_status_amount');
            $table->unique(['purchase_invoice_id', 'payments_module_id'], 'unique_invoice_payment_utilization');
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
