<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add DPR behavioral tracking tables
     */
    public function up(): void
    {
        // Add DPR lifecycle state if not exists
        if (!Schema::hasColumn('daily_progress_reports', 'lifecycle_state')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->enum('lifecycle_state', ['draft', 'verified', 'locked', 'paid'])->default('draft')->after('site_id');
                $table->timestamp('verified_at')->nullable()->after('lifecycle_state');
                $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
                
                // Indexes
                $table->index('lifecycle_state', 'idx_dpr_lifecycle_state');
                
                // Foreign key
                $table->foreign('verified_by')->references('id')->on('users');
            });
        }
        
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
        
        // Add report snapshot table
        if (!Schema::hasTable('report_snapshots')) {
            Schema::create('report_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('report_type', 50);
                $table->string('report_key', 100);
                $table->date('report_date');
                $table->json('report_data');
                $table->decimal('total_amount', 15, 2);
                $table->unsignedBigInteger('created_by');
                $table->timestamp('created_at');
                
                // Indexes
                $table->unique(['report_type', 'report_key', 'report_date'], 'idx_report_unique');
                $table->index('report_type', 'idx_report_type');
                $table->index('report_date', 'idx_report_date');
                
                // Foreign key
                $table->foreign('created_by')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
        Schema::dropIfExists('dpr_anomalies');
        Schema::dropIfExists('dpr_edit_history');
        
        if (Schema::hasColumn('daily_progress_reports', 'lifecycle_state')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->dropForeign(['verified_by']);
                $table->dropIndex('idx_dpr_lifecycle_state');
                $table->dropColumn(['lifecycle_state', 'verified_at', 'verified_by']);
            });
        }
    }
};
