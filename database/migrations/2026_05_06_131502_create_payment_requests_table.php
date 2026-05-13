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
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transaction_flow_id', 50)->nullable()->index('idx_payment_request_transaction_flow');
            $table->unsignedBigInteger('po_id')->nullable();
            $table->string('type', 50)->default('invoice_payment');
            $table->string('idempotency_key', 64)->nullable()->unique('unique_idempotency_key');
            $table->unsignedBigInteger('purchase_invoice_id')->nullable();
            $table->decimal('requested_amount', 15);
            $table->decimal('approved_amount', 15)->nullable();
            $table->date('payment_date');
            $table->enum('status', ['pending', 'approved', 'partially_approved', 'rejected', 'partially_paid', 'paid'])->default('pending')->index('idx_pr_status');
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('requested_by')->index('payment_requests_requested_by_foreign');
            $table->unsignedBigInteger('approved_by')->nullable()->index('payment_requests_approved_by_foreign');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('net_payable_snapshot', 15)->nullable();
            $table->decimal('advance_used_snapshot', 15)->nullable();
            $table->decimal('paid_amount_snapshot', 15)->nullable();
            $table->decimal('active_requests_snapshot', 15)->nullable();
            $table->timestamps();

            $table->index(['purchase_invoice_id', 'status'], 'idx_pr_invoice_status');
            $table->index(['po_id', 'type']);
            $table->index(['purchase_invoice_id', 'type']);
            $table->index(['status']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
