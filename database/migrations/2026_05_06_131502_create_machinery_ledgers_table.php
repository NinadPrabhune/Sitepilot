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
        Schema::create('machinery_ledgers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('machinery_id');
            $table->unsignedBigInteger('workspace_id');
            $table->enum('entry_direction', ['credit', 'debit']);
            $table->string('entry_type');
            $table->enum('ledger_type', ['internal_cost', 'payable', 'expense'])->default('payable')->index('idx_ledger_type');
            $table->enum('cost_category', ['machine', 'diesel', 'maintenance', 'operator', 'advance', 'other'])->default('machine');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('dpr_id')->nullable()->index('idx_dpr_id');
            $table->decimal('amount', 15);
            $table->decimal('running_balance', 15)->default(0);
            $table->date('date');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->boolean('is_reversal')->default(false)->index('idx_is_reversal');
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index('idx_reversal_of');
            $table->unsignedBigInteger('payment_request_id')->nullable()->index('idx_payment_request_id');
            $table->enum('dpr_payment_status', ['unpaid', 'partial', 'paid'])->nullable();
            $table->timestamps();

            $table->index(['ledger_type', 'cost_category'], 'idx_ledger_cost_category');
            $table->index(['is_reversal', 'reversal_of_id']);
            $table->index(['machinery_id', 'date']);
            $table->index(['payment_request_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->unique(['reference_type', 'reference_id', 'payment_request_id'], 'unique_reference_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machinery_ledgers');
    }
};
