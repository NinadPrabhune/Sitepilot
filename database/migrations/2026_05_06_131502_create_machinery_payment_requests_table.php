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
        Schema::create('machinery_payment_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('machinery_id');
            $table->unsignedBigInteger('supplier_id')->index('mp_supplier');
            $table->unsignedBigInteger('workspace_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('credits', 15);
            $table->decimal('debits', 15);
            $table->decimal('net_payable', 15);
            $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'locked', 'paid', 'rejected', 'hold'])->default('draft');
            $table->json('audit_snapshot');
            $table->string('idempotency_key', 64)->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('requested_by')->index('machinery_payment_requests_requested_by_foreign');
            $table->unsignedBigInteger('submitted_by')->nullable()->index('machinery_payment_requests_submitted_by_foreign');
            $table->unsignedBigInteger('verified_by')->nullable()->index('machinery_payment_requests_verified_by_foreign');
            $table->unsignedBigInteger('approved_by')->nullable()->index('machinery_payment_requests_approved_by_foreign');
            $table->unsignedBigInteger('locked_by')->nullable()->index('machinery_payment_requests_locked_by_foreign');
            $table->unsignedBigInteger('paid_by')->nullable()->index('machinery_payment_requests_paid_by_foreign');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['machinery_id', 'workspace_id', 'period_start', 'period_end'], 'mp_mach_ws_period');
            $table->index(['status', 'workspace_id'], 'mp_status_ws');
            $table->unique(['workspace_id', 'idempotency_key'], 'mp_ws_idempotency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machinery_payment_requests');
    }
};
