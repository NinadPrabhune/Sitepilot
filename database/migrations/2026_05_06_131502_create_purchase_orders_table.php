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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transaction_flow_id', 50)->nullable()->index('idx_po_transaction_flow');
            $table->string('po_number');
            $table->date('po_date');
            $table->string('supplier_invoice_number')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable()->index('purchase_orders_supplier_id_foreign');
            $table->decimal('grand_total', 15)->default(0);
            $table->decimal('invoiced_amount', 15)->default(0);
            $table->enum('invoiced_status', ['not_invoiced', 'partially_invoiced', 'fully_invoiced'])->default('not_invoiced');
            $table->date('delivery_date')->nullable()->index('idx_purchase_orders_delivery_date');
            $table->text('delivery_address')->nullable();
            $table->string('reference_file')->nullable();
            $table->text('delivery_terms_conditions')->nullable();
            $table->text('payment_terms_conditions')->nullable();
            $table->text('remark')->nullable();
            $table->text('assign_to')->nullable();
            $table->string('po_pdf')->nullable();
            $table->string('status')->default('Pending')->index('idx_purchase_orders_status');
            $table->date('closed_date')->nullable();
            $table->enum('tax_type', ['cgst', 'igst'])->default('cgst')->index('idx_purchase_orders_tax_type');
            $table->decimal('total_taxable_value', 15)->default(0);
            $table->decimal('total_cgst', 15)->default(0);
            $table->decimal('total_sgst', 15)->default(0);
            $table->decimal('total_igst', 15)->default(0);
            $table->decimal('total_tax', 15)->default(0);
            $table->decimal('total_discount', 15)->default(0);
            $table->decimal('additional_charge', 15)->default(0);
            $table->decimal('additional_deduction', 15)->default(0);
            $table->decimal('additional_discount', 15)->default(0);
            $table->unsignedBigInteger('site_id')->nullable()->index('purchase_orders_site_id_foreign');
            $table->unsignedBigInteger('created_by')->index('purchase_orders_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index('purchase_orders_workspace_id_foreign');
            $table->unsignedBigInteger('indent_id')->nullable()->index('purchase_orders_indent_id_foreign');
            $table->text('description')->nullable();
            $table->decimal('total_amount', 15)->default(0);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('flag_reason')->nullable();
            $table->text('short_close_reason')->nullable();
            $table->timestamp('short_closed_at')->nullable();
            $table->unsignedBigInteger('short_closed_by')->nullable()->index('purchase_orders_short_closed_by_foreign');
            $table->enum('payment_flag', ['pending', 'partial_received', 'fully_received'])->default('pending')->comment('DEPRECATED: Use invoiced_status instead');
            $table->timestamps();
            $table->softDeletes();
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->index(['workspace_id', 'id'], 'idx_po_scope');
            $table->index(['site_id', 'id'], 'idx_po_site_id_id');
            $table->index(['site_id', 'po_number'], 'idx_po_site_id_number');
            $table->unique(['workspace_id', 'po_number'], 'unique_po_number_per_workspace');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
