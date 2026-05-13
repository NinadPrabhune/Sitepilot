<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add DPR lifecycle states and audit tracking
     */
    public function up(): void
    {
        Schema::table('daily_progress_reports', function (Blueprint $table) {
            // Add columns individually with existence checks
            if (!Schema::hasColumn('daily_progress_reports', 'lifecycle_state')) {
                $table->enum('lifecycle_state', ['draft', 'verified', 'locked', 'paid'])->default('draft')->after('site_id');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('lifecycle_state');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('verified_at');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('locked_at');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'locked_by')) {
                $table->unsignedBigInteger('locked_by')->nullable()->after('verified_by');
            }
            if (!Schema::hasColumn('daily_progress_reports', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('locked_by');
            }
            
            // Add indexes only if they don't exist
            if (!Schema::hasIndex('daily_progress_reports', 'idx_dpr_lifecycle_state')) {
                $table->index('lifecycle_state', 'idx_dpr_lifecycle_state');
            }
            if (!Schema::hasIndex('daily_progress_reports', 'idx_dpr_state_date')) {
                $table->index(['lifecycle_state', 'date'], 'idx_dpr_state_date');
            }
            
            // Add foreign keys only if columns exist and don't already have constraints
            if (Schema::hasColumn('daily_progress_reports', 'verified_by')) {
                try {
                    $table->foreign('verified_by')->references('id')->on('users');
                } catch (\Exception $e) {
                    // Foreign key might already exist, continue
                }
            }
            if (Schema::hasColumn('daily_progress_reports', 'locked_by')) {
                try {
                    $table->foreign('locked_by')->references('id')->on('users');
                } catch (\Exception $e) {
                    // Foreign key might already exist, continue
                }
            }
            if (Schema::hasColumn('daily_progress_reports', 'paid_by')) {
                try {
                    $table->foreign('paid_by')->references('id')->on('users');
                } catch (\Exception $e) {
                    // Foreign key might already exist, continue
                }
            }
        });
        
        // Add DPR edit tracking table
        if (!Schema::hasTable('dpr_edit_history')) {
            Schema::create('dpr_edit_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dpr_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('action', ['created', 'updated', 'verified', 'locked', 'paid', 'reverted']);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('created_at');
                
                // Indexes
                $table->index('dpr_id', 'idx_dpr_edit_dpr_id');
                $table->index('user_id', 'idx_dpr_edit_user_id');
                $table->index(['dpr_id', 'created_at'], 'idx_dpr_edit_timeline');
                
                // Foreign keys
                $table->foreign('dpr_id')->references('id')->on('daily_progress_reports')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
        
        // Add DPR anomaly tracking table
        if (!Schema::hasTable('dpr_anomalies')) {
            Schema::create('dpr_anomalies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dpr_id');
                $table->enum('anomaly_type', ['excessive_edits', 'consumption_spike', 'timing_mismatch', 'duplicate_entry', 'suspicious_pattern']);
                $table->text('description');
                $table->json('anomaly_data')->nullable();
                $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->enum('status', ['open', 'investigating', 'resolved', 'false_positive'])->default('open');
                $table->timestamp('detected_at');
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                
                // Indexes
                $table->index('dpr_id', 'idx_anomaly_dpr_id');
                $table->index('anomaly_type', 'idx_anomaly_type');
                $table->index('severity', 'idx_anomaly_severity');
                $table->index('status', 'idx_anomaly_status');
                
                // Foreign keys
                $table->foreign('dpr_id')->references('id')->on('daily_progress_reports')->onDelete('cascade');
                $table->foreign('resolved_by')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dpr_anomalies');
        Schema::dropIfExists('dpr_edit_history');
        
        if (Schema::hasColumn('daily_progress_reports', 'lifecycle_state')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->dropForeign(['verified_by']);
                $table->dropForeign(['locked_by']);
                $table->dropForeign(['paid_by']);
                $table->dropIndex('idx_dpr_state_date');
                $table->dropIndex('idx_dpr_lifecycle_state');
                $table->dropColumn(['lifecycle_state', 'verified_at', 'locked_at', 'paid_at', 'verified_by', 'locked_by', 'paid_by']);
            });
        }
    }
};
