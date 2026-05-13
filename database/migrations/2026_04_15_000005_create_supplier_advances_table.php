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
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('po_id')->nullable()->constrained('purchase_orders')->onDelete('cascade');
            $table->foreignId('site_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->foreignId('workspace_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('advance_number')->unique();
            $table->date('advance_date');
            $table->string('source')->default('po'); // po, manual
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('utilized_amount', 15, 2)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->string('status')->default('pending'); // pending, approved, paid, cancelled
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('payment_proof_file')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->string('transaction_flow_id')->nullable();
            $table->boolean('locked_to_po')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('po_id');
            $table->index('site_id');
            $table->index('workspace_id');
            $table->index('status');
            $table->index('advance_date');
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
