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
        // SAFETY CHECK: Only create if table doesn't exist
        if (!Schema::hasTable('payment_module_allocations')) {
            Schema::create('payment_module_allocations', function (Blueprint $table) {
                $table->id();
                
                // Only add foreign key if payments_module table exists
                if (Schema::hasTable('payments_module')) {
                    $table->foreignId('payment_module_id')->constrained('payments_module')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('payment_module_id');
                }
                
                $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->decimal('allocated_amount', 15, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['payment_module_id', 'purchase_invoice_id'], 'idx_payment_invoice');
                $table->index(['payment_module_id', 'purchase_order_id'], 'idx_payment_po');
            });
            
            // Add foreign key after table creation if payments_module exists now
            if (Schema::hasTable('payments_module') && !Schema::hasTable('payment_module_allocations_temp')) {
                Schema::table('payment_module_allocations', function (Blueprint $table) {
                    $table->foreign('payment_module_id')->references('id')->on('payments_module')->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_module_allocations');
    }
};
