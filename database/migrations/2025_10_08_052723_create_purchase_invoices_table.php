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

    Schema::dropIfExists('purchase_invoices');

    Schema::create('purchase_invoices', function (Blueprint $table) {
        $table->id();

        $table->string('invoice_number')->unique();
        $table->date('invoice_date');
        
        $table->string('supplier_invoice_number')->nullable(); // stores filename or path
        
        $table->unsignedBigInteger('supplier_id');
        $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');

        $table->decimal('total_amount', 12, 2)->default(0.00);
        $table->enum('status', ['Pending', 'Approved', 'Cancelled'])->default('Pending');
        $table->string('invoice_file')->nullable(); // stores filename or path


        $table->unsignedBigInteger('site_id')->nullable();
        $table->foreign('site_id')->references('id')->on('sites')->onDelete('set null');

        $table->unsignedBigInteger('created_by')->default(0);
        $table->unsignedBigInteger('workspace_id')->default(0);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
