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
        Schema::create('machinery_ledger', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('machinery_id')->index();
            $table->unsignedBigInteger('workspace_id')->index('machinery_ledger_workspace_id_foreign');
            $table->enum('entry_direction', ['credit', 'debit'])->index();
            $table->enum('entry_type', ['reading', 'diesel', 'maintenance', 'advance', 'payment', 'transfer'])->index();
            $table->string('ledger_type')->nullable();
            $table->string('cost_category')->nullable();
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('dpr_id')->nullable();
            $table->decimal('amount', 15);
            $table->decimal('running_balance', 15);
            $table->date('date')->index();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->unsignedBigInteger('reversed_entry_id')->nullable()->index();
            $table->boolean('is_reversal')->default(false);
            $table->boolean('is_locked')->default(false)->index();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable()->index('machinery_ledger_locked_by_foreign');
            $table->timestamps();
            $table->softDeletes()->index();
            $table->unsignedBigInteger('payment_request_id')->nullable()->index('machinery_ledger_payment_request_id_foreign');

            $table->index(['is_locked', 'date'], 'idx_locked_date');
            $table->index(['machinery_id', 'date'], 'idx_machinery_date');
            $table->index(['reference_type', 'reference_id'], 'idx_reference');
            $table->index(['is_reversal', 'date'], 'idx_reversal_date');
            $table->index(['machinery_id', 'date']);
            $table->index(['machinery_id', 'date', 'is_reversal']);
            $table->index(['machinery_id', 'workspace_id', 'date', 'id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['machinery_id', 'workspace_id', 'date', 'payment_request_id'], 'ml_mach_ws_date_pr');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machinery_ledger');
    }
};
