<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machinery_id')->constrained()->onDelete('restrict');
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->foreignId('workspace_id')->constrained()->onDelete('restrict');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('credits', 15, 2);
            $table->decimal('debits', 15, 2);
            $table->decimal('net_payable', 15, 2);
            $table->enum('status', ['draft', 'submitted', 'verified', 'approved', 'locked', 'paid', 'rejected', 'hold'])->default('draft');
            $table->json('audit_snapshot');
            $table->string('idempotency_key', 64)->nullable();
            // CRITICAL: Idempotency scoped to workspace (not global)
            $table->unique(['workspace_id', 'idempotency_key'], 'mp_ws_idempotency');
            $table->text('remarks')->nullable();
            $table->foreignId('requested_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('locked_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            // CRITICAL: Performance indexes for scale
            $table->index(['machinery_id', 'workspace_id', 'period_start', 'period_end'], 'mp_mach_ws_period');
            $table->index(['status', 'workspace_id'], 'mp_status_ws'); // For filtering by status
            $table->index('supplier_id', 'mp_supplier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_payment_requests');
    }
};
