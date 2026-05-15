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
        Schema::create('supplier_advances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transaction_flow_id', 50)->nullable()->index('idx_advance_transaction_flow');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('po_id')->nullable();
            $table->boolean('locked_to_po')->default(false);
            $table->unsignedBigInteger('site_id')->nullable()->index('supplier_advances_site_id_foreign');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('advance_number');
            $table->date('advance_date')->index();
            $table->enum('source', ['po', 'manual'])->default('po');
            $table->decimal('amount', 15);
            $table->decimal('allocated_amount', 15)->default(0);
            $table->decimal('utilized_amount', 15)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->string('status')->default('pending');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('reservation_expires_at')->nullable()->index();
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('payment_proof_file')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['po_id', 'supplier_id', 'workspace_id', 'site_id'], 'idx_po_supplier_workspace_site');
            $table->index(['is_locked', 'status']);
            $table->index(['po_id', 'supplier_id']);
            $table->index(['supplier_id', 'po_id']);
            $table->index(['supplier_id', 'status'], 'supplier_advances_supplier_id_status_remaining_amount_index');
            $table->unique(['site_id', 'advance_number'], 'unique_advance_number_per_site');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_advances');
    }
};
