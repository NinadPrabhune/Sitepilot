<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Create advance_audit_logs table for full audit trail
     */
    public function up(): void
    {
        if (!Schema::hasTable('advance_audit_logs')) {
            Schema::create('advance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advance_id');
            $table->enum('action', ['created', 'allocated', 'reversed', 'locked', 'unlocked', 'approved', 'paid']);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('transaction_flow_id', 50)->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->timestamps();

            $table->foreign('advance_id')->references('id')->on('supplier_advances')->onDelete('cascade');
            $table->index('advance_id');
            $table->index('action');
            $table->index('created_at');
            $table->index('transaction_flow_id');
            $table->index(['workspace_id', 'site_id'], 'idx_workspace_site');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advance_audit_logs');
    }
};
