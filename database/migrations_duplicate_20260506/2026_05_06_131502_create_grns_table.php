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
        Schema::create('grns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('grn_number');
            $table->enum('grn_type', ['against_po', 'direct'])->default('against_po')->index();
            $table->unsignedBigInteger('po_id')->nullable()->index('grns_po_id_foreign');
            $table->unsignedBigInteger('supplier_id')->index('grns_supplier_id_foreign');
            $table->unsignedBigInteger('site_id')->index('grns_site_id_foreign');
            $table->date('grn_date');
            $table->string('supplier_invoice_number')->nullable()->index();
            $table->date('supplier_invoice_date')->nullable();
            $table->decimal('total_amount', 15)->default(0);
            $table->string('tax_type')->nullable();
            $table->decimal('total_taxable_value', 15)->default(0);
            $table->decimal('total_cgst', 15)->default(0);
            $table->decimal('total_sgst', 15)->default(0);
            $table->decimal('total_igst', 15)->default(0);
            $table->decimal('total_tax', 15)->default(0);
            $table->string('delivery_challan_number')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('gate_entry_number')->nullable();
            $table->string('delivery_challan_file')->nullable();
            $table->string('reference_file')->nullable();
            $table->text('grn_pdf')->nullable();
            $table->text('description')->nullable();
            $table->string('received_by')->nullable();
            $table->text('remarks')->nullable();
            $table->text('assign_to')->nullable();
            $table->string('status')->default('Pending');
            $table->boolean('is_locked')->default(false);
            $table->unsignedBigInteger('created_by')->index('grns_created_by_foreign');
            $table->unsignedBigInteger('workspace_id')->index('grns_workspace_id_foreign');
            $table->timestamps();
            $table->softDeletes();
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->index(['site_id', 'id'], 'idx_grn_scope');
            $table->index(['site_id', 'id'], 'idx_grn_site_id_id');
            $table->index(['site_id', 'grn_number'], 'idx_grn_site_id_number');
            $table->unique(['site_id', 'grn_number'], 'unique_grn_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grns');
    }
};
