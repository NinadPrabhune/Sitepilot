<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add warning override tracking tables
     */
    public function up(): void
    {
        // Warning overrides table
        if (!Schema::hasTable('warning_overrides')) {
            Schema::create('warning_overrides', function (Blueprint $table) {
                $table->id();
                $table->string('override_id', 50)->unique(); // Unique override ID
                $table->unsignedBigInteger('user_id');
                $table->enum('entity_type', ['dpr', 'diesel', 'activity', 'other'])->default('dpr');
                $table->unsignedBigInteger('entity_id')->nullable(); // DPR ID, etc.
                $table->string('warning_type', 50);
                $table->text('warning_message');
                $table->text('reason');
                $table->timestamp('created_at');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                
                // Indexes
                $table->index('user_id', 'idx_override_user');
                $table->index('entity_type', 'idx_override_entity_type');
                $table->index('warning_type', 'idx_override_warning_type');
                $table->index('created_at', 'idx_override_created');
                $table->index(['user_id', 'created_at'], 'idx_override_user_date');
                
                // Foreign keys
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        // User warning metrics table
        if (!Schema::hasTable('user_warning_metrics')) {
            Schema::create('user_warning_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('date');
                $table->integer('total_overrides')->default(0);
                $table->json('warning_types')->nullable(); // Array of warning types
                $table->timestamps();
                
                // Indexes
                $table->unique(['user_id', 'date'], 'idx_user_date_unique');
                $table->index('date', 'idx_metrics_date');
                $table->index('total_overrides', 'idx_metrics_overrides');
                
                // Foreign key
                $table->foreign('user_id')->references('id')->on('users');
            });
        }

        // Add warning indicators to existing tables
        if (!Schema::hasColumn('daily_progress_reports', 'warning_overrides')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->json('warning_overrides')->nullable()->after('lifecycle_state');
                $table->integer('warning_override_count')->default(0)->after('warning_overrides');
                
                $table->index('warning_override_count', 'idx_dpr_warning_count');
            });
        }

        if (!Schema::hasColumn('daily_consumption_masters', 'warning_overrides')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->json('warning_overrides')->nullable()->after('created_by');
                $table->integer('warning_override_count')->default(0)->after('warning_overrides');
                
                $table->index('warning_override_count', 'idx_diesel_warning_count');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warning_overrides');
        Schema::dropIfExists('user_warning_metrics');
        
        if (Schema::hasColumn('daily_progress_reports', 'warning_overrides')) {
            Schema::table('daily_progress_reports', function (Blueprint $table) {
                $table->dropIndex('idx_dpr_warning_count');
                $table->dropColumn(['warning_overrides', 'warning_override_count']);
            });
        }
        
        if (Schema::hasColumn('daily_consumption_masters', 'warning_overrides')) {
            Schema::table('daily_consumption_masters', function (Blueprint $table) {
                $table->dropIndex('idx_diesel_warning_count');
                $table->dropColumn(['warning_overrides', 'warning_override_count']);
            });
        }
    }
};
