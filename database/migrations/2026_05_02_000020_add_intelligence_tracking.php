<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add intelligence tracking tables
     */
    public function up(): void
    {
        // Reason intelligence tracking
        if (!Schema::hasTable('reason_intelligence')) {
            Schema::create('reason_intelligence', function (Blueprint $table) {
                $table->id();
                $table->string('reason');
                $table->string('category', 50);
                $table->float('confidence');
                $table->float('weight');
                $table->boolean('is_legitimate');
                $table->json('analysis')->nullable();
                $table->integer('usage_count')->default(1);
                $table->timestamps();
                
                $table->index('category', 'idx_reason_category');
                $table->index('is_legitimate', 'idx_reason_legitimate');
                $table->unique('reason', 'idx_reason_unique');
            });
        }

        // User trust tracking
        if (!Schema::hasTable('user_trust_log')) {
            Schema::create('user_trust_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('trust_level', 20);
                $table->boolean('trusted');
                $table->json('metrics')->nullable();
                $table->timestamp('created_at');
                
                $table->index('user_id', 'idx_trust_user');
                $table->index('trust_level', 'idx_trust_level');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        // Trust review schedule
        if (!Schema::hasTable('trust_review_schedule')) {
            Schema::create('trust_review_schedule', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('review_date');
                $table->string('current_level', 20);
                $table->boolean('processed')->default(false);
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                
                $table->index('review_date', 'idx_review_date');
                $table->index('processed', 'idx_review_processed');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        // Financial gate blocks
        if (!Schema::hasTable('financial_gate_blocks')) {
            Schema::create('financial_gate_blocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('entity_type', 20);
                $table->unsignedBigInteger('entity_id');
                $table->text('reason');
                $table->json('requirements')->nullable();
                $table->json('warnings')->nullable();
                $table->timestamps();
                
                $table->index('user_id', 'idx_gate_user');
                $table->index('entity_type', 'idx_gate_entity');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        // Financial escalations
        if (!Schema::hasTable('financial_escalations')) {
            Schema::create('financial_escalations', function (Blueprint $table) {
                $table->id();
                $table->string('escalation_id', 50)->unique();
                $table->unsignedBigInteger('user_id');
                $table->string('entity_type', 20);
                $table->unsignedBigInteger('entity_id');
                $table->string('escalation_type', 30);
                $table->text('reason');
                $table->json('requirements')->nullable();
                $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval');
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->text('approver_comments')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();
                
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                
                $table->index('user_id', 'idx_fin_escalation_user');
                $table->index('escalation_type', 'idx_fin_escalation_type');
                $table->index('status', 'idx_fin_escalation_status');
                $table->foreign('user_id')->references('id')->on('users');
                $table->foreign('approver_id')->references('id')->on('users');
            });
        }

        // Financial postings
        if (!Schema::hasTable('financial_postings')) {
            Schema::create('financial_postings', function (Blueprint $table) {
                $table->id();
                $table->string('posting_id', 50)->unique();
                $table->string('entity_type', 20);
                $table->unsignedBigInteger('entity_id');
                $table->decimal('amount', 15, 2);
                $table->unsignedBigInteger('posted_by');
                $table->enum('status', ['posted', 'reversed'])->default('posted');
                $table->timestamps();
                
                $table->index('entity_type', 'idx_posting_entity');
                $table->index('posted_by', 'idx_posting_user');
                $table->index('status', 'idx_posting_status');
                $table->foreign('posted_by')->references('id')->on('users');
            });
        }

        // Add trust level to users
        if (!Schema::hasColumn('users', 'trust_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('trust_level', 20)->default('standard')->after('escalation_level');
                $table->date('trust_review_date')->nullable()->after('trust_level');
                
                $table->index('trust_level', 'idx_user_trust_level');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_postings');
        Schema::dropIfExists('financial_escalations');
        Schema::dropIfExists('financial_gate_blocks');
        Schema::dropIfExists('trust_review_schedule');
        Schema::dropIfExists('user_trust_log');
        Schema::dropIfExists('reason_intelligence');
        
        if (Schema::hasColumn('users', 'trust_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('idx_user_trust_level');
                $table->dropColumn(['trust_level', 'trust_review_date']);
            });
        }
    }
};
