<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical ledger table is machinery_ledger (singular).
     * This duplicate migration is kept only as a no-op reference;
     * it will never create or alter any table because the guard
     * checks for the already-existing machinery_ledger first.
     */
    public function up(): void
    {
        if (!Schema::hasTable('machinery_ledger')) {
            Schema::create('machinery_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');
                $table->enum('entry_direction', ['credit', 'debit']);
                $table->string('entry_type');
                $table->enum('ledger_type', ['internal_cost', 'payable', 'expense'])->default('payable')->index('idx_ledger_type');
                $table->enum('cost_category', ['machine', 'diesel', 'maintenance', 'operator', 'advance', 'other'])->default('machine');
                $table->string('reference_type', 50)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('dpr_id')->nullable()->index('idx_dpr_id');
                $table->decimal('amount', 15, 2);
                $table->decimal('running_balance', 15, 2)->default(0);
                $table->date('date');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('idempotency_key', 100)->nullable();
                $table->boolean('is_reversal')->default(false)->index('idx_is_reversal');
                $table->unsignedBigInteger('reversal_of_id')->nullable()->index('idx_reversal_of');
                $table->unsignedBigInteger('payment_request_id')->nullable()->index('idx_payment_request_id');
                $table->enum('dpr_payment_status', ['unpaid', 'partial', 'paid'])->nullable();
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamps();

                $table->index(['ledger_type', 'cost_category'], 'idx_ledger_cost_category');
                $table->index(['is_reversal', 'reversal_of_id']);
                $table->index(['machinery_id', 'date']);
                $table->index(['payment_request_id']);
                $table->index(['reference_type', 'reference_id']);
                $table->unique(['reference_type', 'reference_id', 'payment_request_id'], 'unique_reference_payment');
                $table->index(['machinery_id', 'workspace_id', 'date', 'id']);
            });
        }
    }

    public function down(): void
    {
        // Safe no-op: machinery_ledger is the canonical table and is managed
        // by other migrations; this stub should never drop live data.
    }
};
