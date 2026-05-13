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
        
        Schema::dropIfExists('payments_module');
        
        Schema::create('payments_module', function (Blueprint $table) {
            $table->id();
            
            $table->string('payment_number')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()
                  ->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('projects')->cascadeOnDelete();

            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_type', ['against_invoice', 'advance']);
            $table->string('mode')->nullable();
            $table->string('reference_number')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->text('notes')->nullable();
            $table->string('payment_proff_file')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_modules');
    }
};
