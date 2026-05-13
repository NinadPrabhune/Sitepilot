<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('supplier_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('workspace_id')->constrained('work_spaces')->onDelete('cascade');
            
            // Entry details
            $table->enum('entry_direction', ['credit', 'debit'])->default('debit');
            $table->string('entry_type')->default('diesel'); // diesel, payment, adjustment
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('running_balance', 15, 2)->default(0);
            
            // Reference to source
            $table->string('reference_type')->nullable(); // DailyConsumptionMaster, SupplierPayment
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Metadata
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            
            // Reversal tracking
            $table->boolean('is_reversal')->default(false);
            $table->unsignedBigInteger('reversed_entry_id')->nullable();
            
            // Lock tracking
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['supplier_id', 'date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('is_reversal');
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplier_ledger');
    }
};
