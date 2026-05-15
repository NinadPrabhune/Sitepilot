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
        Schema::create('payments_module', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('idempotency_key', 64)->nullable()->unique('unique_payment_idempotency');
            $table->string('payment_number');
            $table->unsignedBigInteger('supplier_id')->index('payments_module_supplier_id_foreign');
            $table->unsignedBigInteger('purchase_invoice_id')->nullable()->index('idx_invoice_id');
            $table->unsignedBigInteger('site_id')->index('payments_module_site_id_foreign');
            $table->date('payment_date');
            $table->decimal('amount', 15);
            $table->enum('payment_type', ['advance_against_po', 'against_po', 'against_invoice', 'mixed', 'on_account'])->default('against_po');
            $table->enum('status', ['completed', 'pending', 'cancelled'])->default('completed');
            $table->string('mode')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('created_by')->index('payments_module_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index('payments_module_workspace_id_foreign');
            $table->text('notes')->nullable();
            $table->string('payment_proff_file')->nullable();
            $table->string('payment_pdf')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index('idx_po_id');
            $table->unsignedBigInteger('payment_request_id')->nullable()->index('idx_payment_request_id');

            $table->index(['site_id', 'id'], 'idx_payment_scope');
            $table->index(['site_id', 'id'], 'idx_payment_site_id_id');
            $table->index(['site_id', 'payment_number'], 'idx_payment_site_id_number');
            $table->index(['purchase_invoice_id'], 'idx_pm_invoice');
            $table->index(['purchase_order_id', 'payment_type'], 'idx_pm_po_type');
            $table->index(['purchase_invoice_id'], 'idx_purchase_invoice_id');
            $table->index(['purchase_invoice_id'], 'payments_module_purchase_invoice_id_foreign');
            $table->unique(['site_id', 'payment_number'], 'unique_payment_number_per_site');
            $table->unique(['payment_request_id'], 'unique_payment_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_module');
    }
};
