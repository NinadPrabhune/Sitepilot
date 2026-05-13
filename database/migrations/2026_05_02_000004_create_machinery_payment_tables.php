<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create machinery payment tables
     */
    public function up(): void
    {
        // Create machinery_payment_requests table
        if (!Schema::hasTable('machinery_payment_requests')) {
            Schema::create('machinery_payment_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('workspace_id');
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('credits', 15, 2)->default(0);
                $table->decimal('debits', 15, 2)->default(0);
                $table->decimal('net_payable', 15, 2)->default(0);
                $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'locked', 'paid', 'rejected', 'hold'])->default('draft');
                $table->unsignedBigInteger('requested_by');
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->unsignedBigInteger('rejected_by')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('audit_data')->nullable();
                $table->timestamps();

                $table->index(['machinery_id', 'period_start', 'period_end']);
                $table->index(['status']);
                $table->index(['supplier_id']);
            });
        }

        // Create machinery_payment_periods table
        if (!Schema::hasTable('machinery_payment_periods')) {
            Schema::create('machinery_payment_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machinery_id');
                $table->unsignedBigInteger('workspace_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_locked')->default(false);
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('payment_request_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index(['machinery_id', 'start_date', 'end_date']);
                $table->index(['payment_request_id']);
                $table->index(['is_locked']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_payment_periods');
        Schema::dropIfExists('machinery_payment_requests');
    }
};
