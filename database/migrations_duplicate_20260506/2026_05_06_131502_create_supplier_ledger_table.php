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
        Schema::create('supplier_ledger', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('workspace_id')->index('supplier_ledger_workspace_id_foreign');
            $table->enum('entry_direction', ['credit', 'debit'])->default('debit');
            $table->string('entry_type')->default('diesel');
            $table->decimal('amount', 15)->default(0);
            $table->decimal('running_balance', 15)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->boolean('is_reversal')->default(false)->index();
            $table->unsignedBigInteger('reversed_entry_id')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'idx_reference');
            $table->index(['is_reversal', 'date'], 'idx_reversal_date');
            $table->index(['supplier_id', 'date'], 'idx_supplier_date');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['supplier_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger');
    }
};
