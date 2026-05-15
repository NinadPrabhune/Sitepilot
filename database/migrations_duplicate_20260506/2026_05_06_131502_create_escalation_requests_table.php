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
        Schema::create('escalation_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_id', 50)->unique();
            $table->unsignedBigInteger('user_id')->index('idx_escalation_user');
            $table->string('action', 50);
            $table->enum('entity_type', ['dpr', 'diesel', 'activity', 'other'])->default('dpr');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('escalation_level', ['supervisor', 'manager'])->default('supervisor')->index('idx_escalation_level');
            $table->unsignedBigInteger('approver_id')->nullable()->index('escalation_requests_approver_id_foreign');
            $table->text('reason');
            $table->json('conditions')->nullable();
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval')->index('idx_escalation_status');
            $table->text('approver_comments')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable()->index('idx_escalation_created');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->index(['user_id', 'status'], 'idx_escalation_user_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalation_requests');
    }
};
