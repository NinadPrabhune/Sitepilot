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
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('po_id')->nullable()->index('purchase_invoices_po_id_foreign');
            $table->string('transaction_flow_id', 50)->nullable()->index('idx_invoice_transaction_flow');
            $table->enum('grn_type', ['PO', 'DIRECT'])->nullable();
            $table->text('assign_to')->nullable();
            $table->unsignedBigInteger('grn_id')->nullable()->index('purchase_invoices_grn_id_foreign');
            $table->unsignedBigInteger('site_id')->nullable()->index('purchase_invoices_site_id_foreign');
            $table->string('invoice_number');
            $table->enum('invoice_type', ['general_po', 'minor_misc_service', '', ''])->default('general_po');
            $table->date('invoice_date');
            $table->string('supplier_invoice_number')->nullable();
            $table->unsignedBigInteger('supplier_id')->index('purchase_invoices_supplier_id_foreign');
            $table->decimal('total_amount', 12)->default(0);
            $table->enum('status', ['Pending', 'Approved', 'Cancelled'])->default('Pending');
            $table->boolean('is_financially_locked')->default(false)->index();
            $table->timestamp('financially_locked_at')->nullable();
            $table->string('invoice_file')->nullable();
            $table->text('pi_pdf')->nullable();
            $table->string('tax_type')->nullable()->comment('cgst or igst');
            $table->decimal('total_taxable_value', 12)->default(0);
            $table->decimal('total_cgst', 12)->default(0);
            $table->decimal('total_sgst', 12)->default(0);
            $table->decimal('total_igst', 12)->default(0);
            $table->decimal('total_tax', 12)->default(0);
            $table->decimal('total_discount', 12)->default(0);
            $table->decimal('grand_total', 12)->default(0);
            $table->decimal('paid_amount', 15)->default(0);
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('workspace_id')->default(0);
            $table->tinyInteger('payment_request_flag')->default(0);
            $table->string('payment_status')->default('\'\'unpaid\'\'');
            $table->text('ac_payment_status')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('financially_locked_by')->nullable()->index('purchase_invoices_financially_locked_by_foreign');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->index(['site_id', 'id'], 'idx_invoice_scope');
            $table->index(['site_id', 'id'], 'idx_invoice_site_id_id');
            $table->index(['site_id', 'invoice_number'], 'idx_invoice_site_id_number');
            $table->unique(['site_id', 'invoice_number'], 'unique_invoice_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
