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
            $table->id();
            $table->string('po_number')->unique();
            $table->date('po_date');
            $table->string('supplier_invoice_number')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('Pending');
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('workspace_id');
            $table->foreignId('indent_id')->nullable()->constrained('indents')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workspace_id')->references('id')->on('work_spaces')->onDelete('cascade');
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
