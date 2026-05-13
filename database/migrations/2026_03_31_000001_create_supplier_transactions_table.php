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
        if (Schema::hasTable('supplier_transactions')) {
            return;
        }

        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->id();

            // Supplier reference
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');

            // Site/Project reference
            $table->unsignedBigInteger('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('set null');

            // Reference information (polymorphic-like)
            $table->string('reference_type')->nullable(); // e.g., 'invoice', 'payment', 'advance', 'adjustment'
            $table->unsignedBigInteger('reference_id')->nullable();

            // Transaction details
            $table->date('transaction_date');
            $table->decimal('debit', 15, 2)->default(0.00);
            $table->decimal('credit', 15, 2)->default(0.00);
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->text('description')->nullable();

            // Workspace and user tracking
            $table->unsignedBigInteger('workspace_id')->default(0);
            $table->foreign('workspace_id')->references('id')->on('work_spaces')->onDelete('set null');

            $table->unsignedBigInteger('created_by')->default(0);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();

            // Indexes for performance
            $table->index('supplier_id');
            $table->index('site_id');
            $table->index('reference_id');
            $table->index('transaction_date');
            $table->index(['supplier_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
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
