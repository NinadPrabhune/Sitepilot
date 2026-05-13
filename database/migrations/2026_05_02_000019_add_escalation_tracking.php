<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add escalation tracking tables
     */
    public function up(): void
    {
        // Escalation requests table
        if (!Schema::hasTable('escalation_requests')) {
            Schema::create('escalation_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_id', 50)->unique(); // Unique escalation ID
                $table->unsignedBigInteger('user_id');
                $table->string('action', 50); // Action being escalated
                $table->enum('entity_type', ['dpr', 'diesel', 'activity', 'other'])->default('dpr');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->enum('escalation_level', ['supervisor', 'manager'])->default('supervisor');
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->text('reason');
                $table->json('conditions')->nullable();
                $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval');
                $table->text('approver_comments')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('data')->nullable(); // Original action data
                $table->timestamps();
                
                // Timestamps for approval/rejection
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                
                // Indexes
                $table->index('user_id', 'idx_escalation_user');
                $table->index('escalation_level', 'idx_escalation_level');
                $table->index('status', 'idx_escalation_status');
                $table->index('created_at', 'idx_escalation_created');
                $table->index(['user_id', 'status'], 'idx_escalation_user_status');
                
                // Foreign keys
                $table->foreign('user_id')->references('id')->on('users');
                $table->foreign('approver_id')->references('id')->on('users');
            });
        }

        // Add escalation tracking to users table
        if (!Schema::hasColumn('users', 'escalation_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('escalation_level', ['none', 'supervisor', 'manager', 'restricted'])->default('none')->after('email');
                $table->timestamp('escalation_locked_until')->nullable()->after('escalation_level');
                $table->unsignedBigInteger('escalation_locked_by')->nullable()->after('escalation_locked_until');
                
                $table->index('escalation_level', 'idx_user_escalation_level');
                $table->foreign('escalation_locked_by')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_requests');
        
        if (Schema::hasColumn('users', 'escalation_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['escalation_locked_by']);
                $table->dropIndex('idx_user_escalation_level');
                $table->dropColumn(['escalation_level', 'escalation_locked_until', 'escalation_locked_by']);
            });
        }
    }
};
