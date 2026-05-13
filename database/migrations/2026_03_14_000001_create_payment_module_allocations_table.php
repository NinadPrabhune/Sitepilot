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
        // Only create if table doesn't exist
        if (!Schema::hasTable('payment_module_allocations')) {
            Schema::create('payment_module_allocations', function (Blueprint $table) {
                $table->id();
                
                // Only add foreign key if payments_module table exists
                if (Schema::hasTable('payments_module')) {
                    $table->foreignId('payment_module_id')->constrained('payments_module')->onDelete('cascade');
                } else {
                    $table->unsignedBigInteger('payment_module_id');
                }
                
                $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->onDelete('cascade');
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->onDelete('cascade');
                $table->decimal('allocated_amount', 15, 2)->default(0);
                $table->timestamps();
            });
            
            // Add foreign key after table creation if payments_module exists now
            if (Schema::hasTable('payments_module')) {
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
