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
        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transaction_flow_id', 50)->nullable()->index('idx_transaction_transaction_flow');
            $table->enum('grn_type', ['PO', 'DIRECT'])->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('site_id')->nullable()->index('supplier_transactions_site_id_foreign');
            $table->string('reference_type', 20)->default('po')->index();
            $table->unsignedBigInteger('reference_id');
            $table->decimal('reference_amount', 15)->default(0);
            $table->longText('meta')->nullable();
            $table->date('transaction_date')->index('supplier_transactions_date_index');
            $table->dateTime('transaction_datetime')->nullable();
            $table->decimal('debit', 15)->default(0);
            $table->decimal('credit', 15)->default(0);
            $table->decimal('balance', 15);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('created_by')->index('supplier_transactions_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'site_id', 'transaction_date'], 'idx_balance_recalc');
            $table->index(['reference_type', 'reference_id'], 'idx_idempotency');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['supplier_id', 'transaction_date']);
            $table->index(['supplier_id', 'site_id'], 'supplier_transactions_supplier_site_index');
            $table->unique(['reference_type', 'reference_id', 'supplier_id', 'site_id'], 'unique_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_transactions');
    }
};
